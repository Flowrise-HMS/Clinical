<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\CarePlanProblemStrength;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class CarePlanServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected CarePlanService $service;

    protected Branch $branch;

    protected User $author;

    protected Patient $patient;

    protected Encounter $encounter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);

        $this->service = app(CarePlanService::class);
        $this->branch = Branch::factory()->create();
        $this->author = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->actingAs($this->author);
        $this->patient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );
        $this->encounter = Encounter::factory()
            ->forPatient($this->patient)
            ->active()
            ->create(['branch_id' => $this->branch->id]);
    }

    public function test_create_persists_a_draft_plan_using_the_encounter_branch(): void
    {
        $plan = $this->service->create(
            $this->patient,
            $this->encounter,
            CarePlanCategory::NURSING,
            $this->author,
            ['title' => 'Postoperative care'],
        );

        $this->assertSame($this->patient->id, $plan->patient_id);
        $this->assertSame($this->encounter->id, $plan->encounter_id);
        $this->assertSame($this->encounter->branch_id, $plan->branch_id);
        $this->assertSame(CarePlanStatus::DRAFT, $plan->status);
        $this->assertSame('Postoperative care', $plan->title);
    }

    public function test_create_refuses_a_patient_that_does_not_match_the_encounter(): void
    {
        $otherPatient = Patient::withoutEvents(
            fn () => Patient::factory()->create(['branch_id' => $this->branch->id])
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($otherPatient, $this->encounter, CarePlanCategory::NURSING, $this->author);
    }

    public function test_create_refuses_a_finished_encounter(): void
    {
        $this->encounter->update(['status' => EncounterStatus::FINISHED]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($this->patient, $this->encounter->fresh(), CarePlanCategory::NURSING, $this->author);
    }

    public function test_create_refuses_a_second_open_plan_for_the_same_encounter_and_category(): void
    {
        $this->service->create($this->patient, $this->encounter, CarePlanCategory::NURSING, $this->author);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($this->patient, $this->encounter, CarePlanCategory::NURSING, $this->author);
    }

    public function test_activate_refuses_a_plan_without_a_medical_diagnosis(): void
    {
        $plan = $this->completePlan();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->activate($plan);
    }

    public function test_activate_refuses_a_problem_without_a_strength(): void
    {
        $plan = $this->completePlan(withStrength: false);
        $this->attachMedicalDiagnosis($plan);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->activate($plan);
    }

    public function test_activate_refuses_unresolved_allergies(): void
    {
        $plan = $this->completePlan(noKnownAllergies: false);
        $this->attachMedicalDiagnosis($plan);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->activate($plan);
    }

    public function test_activate_refuses_incomplete_routine_care(): void
    {
        $plan = $this->completePlan(withRoutineCare: false);
        $this->attachMedicalDiagnosis($plan);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->activate($plan);
    }

    public function test_activate_warns_but_allows_fewer_than_three_orders(): void
    {
        $plan = $this->completePlan(orderCount: 2);
        $this->attachMedicalDiagnosis($plan);

        $warnings = $this->service->activationWarnings($plan);
        $activated = $this->service->activate($plan);

        $this->assertNotEmpty($warnings);
        $this->assertSame(CarePlanStatus::ACTIVE, $activated->status);
    }

    public function test_activate_allows_incomplete_pes_fields(): void
    {
        $plan = $this->completePlan();
        $this->attachMedicalDiagnosis($plan);
        $plan->diagnoses()->firstOrFail()->update([
            'related_to' => null,
            'as_evidenced_by' => null,
            'problem_statement' => null,
            'label' => 'Acute pain',
            'composed_statement' => 'Acute pain',
        ]);

        $activated = $this->service->activate($plan->fresh());

        $this->assertSame(CarePlanStatus::ACTIVE, $activated->status);
    }

    public function test_activate_sets_active_status_and_timestamp_when_every_guard_passes(): void
    {
        $plan = $this->completePlan();
        $this->attachMedicalDiagnosis($plan);

        $activated = $this->service->activate($plan);

        $this->assertSame(CarePlanStatus::ACTIVE, $activated->status);
        $this->assertNotNull($activated->activated_at);
    }

    public function test_activation_readiness_reports_missing_requirements_and_soft_warnings(): void
    {
        $incomplete = $this->completePlan(orderCount: 1);
        $incompleteReadiness = $this->service->activationReadiness($incomplete);

        $this->assertFalse($incompleteReadiness['is_ready']);
        $this->assertFalse($incompleteReadiness['can_activate']);
        $this->assertSame([], $incompleteReadiness['medical_diagnoses']);

        $medicalItem = collect($incompleteReadiness['items'])->firstWhere('key', 'medical_diagnosis');
        $ordersItem = collect($incompleteReadiness['items'])->firstWhere('key', 'orders');

        $this->assertFalse($medicalItem['passed']);
        $this->assertFalse($ordersItem['passed']);
        $this->assertSame('warning', $ordersItem['severity']);

        $readyPlan = $this->completePlan();
        $this->attachMedicalDiagnosis($readyPlan);
        $ready = $this->service->activationReadiness($readyPlan->fresh());

        $this->assertTrue($ready['is_ready']);
        $this->assertTrue($ready['can_activate']);
        $this->assertCount(1, $ready['medical_diagnoses']);
        $this->assertTrue(collect($ready['items'])->where('severity', 'required')->every(
            fn (array $item): bool => $item['passed']
        ));
    }

    public function test_hold_complete_and_revoke_update_the_plan_lifecycle(): void
    {
        $plan = $this->completePlan();
        $this->attachMedicalDiagnosis($plan);
        $active = $this->service->activate($plan);

        $held = $this->service->hold($active);
        $this->assertSame(CarePlanStatus::ON_HOLD, $held->status);

        $completed = $this->service->complete($held, 'Discharged home');
        $this->assertSame(CarePlanStatus::COMPLETED, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame('Discharged home', $completed->closure_reason);

        $revoked = $this->service->revoke($this->completePlan(), 'Created in error');
        $this->assertSame(CarePlanStatus::REVOKED, $revoked->status);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame('Created in error', $revoked->closure_reason);
    }

    protected function completePlan(
        bool $withStrength = true,
        bool $withRoutineCare = true,
        bool $noKnownAllergies = true,
        int $orderCount = 3,
    ): CarePlan {
        $plan = CarePlan::factory()->create([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'encounter_id' => $this->encounter->id,
            'author_id' => $this->author->id,
            'no_known_allergies' => $noKnownAllergies,
        ]);

        $problem = CarePlanProblem::factory()
            ->for($plan)
            ->create(['identified_by' => $this->author->id]);

        if ($withStrength) {
            CarePlanProblemStrength::factory()
                ->for($problem, 'problem')
                ->create(['identified_by' => $this->author->id]);
        }

        $diagnosis = CarePlanDiagnosis::factory()
            ->for($plan)
            ->for($problem, 'problem')
            ->create(['formulated_by' => $this->author->id]);

        CarePlanOrder::factory()
            ->count($orderCount)
            ->for($diagnosis, 'diagnosis')
            ->sequence(fn ($sequence) => ['sequence' => $sequence->index + 1])
            ->create();

        if ($withRoutineCare) {
            $plan->routineCares()->createMany(
                collect(\Modules\Clinical\Enums\RoutineCareItem::cases())
                    ->reject(fn (\Modules\Clinical\Enums\RoutineCareItem $item): bool => $item === \Modules\Clinical\Enums\RoutineCareItem::OTHER)
                    ->map(fn (\Modules\Clinical\Enums\RoutineCareItem $item): array => [
                        'item' => $item,
                        'specification' => 'As prescribed',
                        'specified_by' => $this->author->id,
                        'specified_at' => now(),
                    ])
                    ->all(),
            );
        }

        return $plan;
    }

    protected function attachMedicalDiagnosis(CarePlan $plan): void
    {
        $diagnosis = EncounterDiagnosis::factory()->create([
            'encounter_id' => $this->encounter->id,
            'patient_id' => $this->patient->id,
            'ordered_by' => $this->author->id,
        ]);

        $this->service->attachMedicalDiagnosis($plan, $diagnosis);
    }
}
