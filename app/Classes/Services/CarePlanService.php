<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\CarePlanRoutineCare;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Patient\Models\Patient;

class CarePlanService
{
    /**
     * @param  array<string, mixed>  $attrs
     */
    public function create(
        Patient $patient,
        Encounter $encounter,
        CarePlanCategory $category,
        User $author,
        array $attrs = [],
    ): CarePlan {
        return DB::transaction(function () use ($patient, $encounter, $category, $author, $attrs): CarePlan {
            $encounter = Encounter::query()->lockForUpdate()->findOrFail($encounter->id);

            $this->assertEncounterCanHavePlan($patient, $encounter);
            $this->assertNoOpenPlan($encounter, $category);

            return CarePlan::query()->create(array_merge($attrs, [
                'patient_id' => $patient->id,
                'encounter_id' => $encounter->id,
                'branch_id' => $encounter->branch_id,
                'category' => $category,
                'status' => CarePlanStatus::DRAFT,
                'author_id' => $author->id,
            ]));
        });
    }

    public function activate(CarePlan $plan): CarePlan
    {
        return DB::transaction(function () use ($plan): CarePlan {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            $encounter = Encounter::query()->lockForUpdate()->findOrFail($plan->encounter_id);

            $this->assertEncounterCanHavePlan($plan->patient, $encounter);

            if ($plan->status === CarePlanStatus::ACTIVE) {
                throw new \InvalidArgumentException(__('Care plan is already active.'));
            }

            if (! in_array($plan->status, [CarePlanStatus::DRAFT, CarePlanStatus::ON_HOLD], true)) {
                throw new \InvalidArgumentException(__('Only draft or on-hold care plans can be activated.'));
            }

            $this->assertNoOtherActivePlan($plan);
            $this->assertActivationRequirements($plan);

            $plan->update([
                'status' => CarePlanStatus::ACTIVE,
                'activated_at' => now(),
            ]);

            return $plan->fresh();
        });
    }

    public function attachMedicalDiagnosis(CarePlan $plan, EncounterDiagnosis $diagnosis): CarePlan
    {
        return DB::transaction(function () use ($plan, $diagnosis): CarePlan {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);
            $diagnosis = EncounterDiagnosis::query()->lockForUpdate()->findOrFail($diagnosis->id);

            if (! $plan->status->isOpen()) {
                throw new \InvalidArgumentException(__('Only open care plans can be updated.'));
            }

            if (
                $diagnosis->encounter_id !== $plan->encounter_id
                || $diagnosis->patient_id !== $plan->patient_id
            ) {
                throw new \InvalidArgumentException(__('Medical diagnosis must belong to the care plan encounter and patient.'));
            }

            $plan->medicalDiagnoses()->syncWithoutDetaching([
                $diagnosis->id => ['id' => (string) Str::uuid()],
            ]);

            return $plan->fresh();
        });
    }

    public function hold(CarePlan $plan): CarePlan
    {
        return DB::transaction(function () use ($plan): CarePlan {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);

            if ($plan->status !== CarePlanStatus::ACTIVE) {
                throw new \InvalidArgumentException(__('Only active care plans can be put on hold.'));
            }

            $plan->update(['status' => CarePlanStatus::ON_HOLD]);

            return $plan->fresh();
        });
    }

    public function complete(CarePlan $plan, ?string $closureReason = null): CarePlan
    {
        return DB::transaction(function () use ($plan, $closureReason): CarePlan {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! in_array($plan->status, [CarePlanStatus::ACTIVE, CarePlanStatus::ON_HOLD], true)) {
                throw new \InvalidArgumentException(__('Only active or on-hold care plans can be completed.'));
            }

            $plan->update([
                'status' => CarePlanStatus::COMPLETED,
                'completed_at' => now(),
                'closure_reason' => $closureReason,
            ]);

            return $plan->fresh();
        });
    }

    public function revoke(CarePlan $plan, ?string $closureReason = null): CarePlan
    {
        return DB::transaction(function () use ($plan, $closureReason): CarePlan {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);

            if (! $plan->status->isOpen()) {
                throw new \InvalidArgumentException(__('Only open care plans can be revoked.'));
            }

            $plan->update([
                'status' => CarePlanStatus::REVOKED,
                'revoked_at' => now(),
                'closure_reason' => $closureReason,
            ]);

            return $plan->fresh();
        });
    }

    protected function assertEncounterCanHavePlan(Patient $patient, Encounter $encounter): void
    {
        if ($encounter->patient_id !== $patient->id) {
            throw new \InvalidArgumentException(__('Patient does not match the encounter.'));
        }

        if (! $encounter->status?->isActive()) {
            throw new \InvalidArgumentException(__('An active encounter is required to create or activate a care plan.'));
        }
    }

    protected function assertNoOpenPlan(Encounter $encounter, CarePlanCategory $category): void
    {
        $hasOpenPlan = CarePlan::query()
            ->where('encounter_id', $encounter->id)
            ->where('category', $category)
            ->whereIn('status', [
                CarePlanStatus::DRAFT,
                CarePlanStatus::ACTIVE,
                CarePlanStatus::ON_HOLD,
            ])
            ->lockForUpdate()
            ->exists();

        if ($hasOpenPlan) {
            throw new \InvalidArgumentException(__('An open care plan already exists for this encounter and category.'));
        }
    }

    protected function assertNoOtherActivePlan(CarePlan $plan): void
    {
        $hasOtherActivePlan = CarePlan::query()
            ->where('encounter_id', $plan->encounter_id)
            ->where('category', $plan->category)
            ->where('status', CarePlanStatus::ACTIVE)
            ->whereKeyNot($plan->id)
            ->lockForUpdate()
            ->exists();

        if ($hasOtherActivePlan) {
            throw new \InvalidArgumentException(__('An active care plan already exists for this encounter and category.'));
        }
    }

    protected function assertActivationRequirements(CarePlan $plan): void
    {
        if (! $plan->medicalDiagnoses()->exists()) {
            throw new \InvalidArgumentException(__('Care plan requires at least one medical diagnosis.'));
        }

        if (CarePlanProblem::query()
            ->where('care_plan_id', $plan->id)
            ->whereDoesntHave('strengths')
            ->exists()) {
            throw new \InvalidArgumentException(__('Every care plan problem requires at least one strength.'));
        }

        if (! $plan->no_known_allergies && ! Allergy::query()
            ->active()
            ->forPatient($plan->patient_id)
            ->exists()) {
            throw new \InvalidArgumentException(__('Record active allergies or confirm no known allergies before activation.'));
        }

        $this->assertRoutineCareIsComplete($plan);
        $this->assertNursingDiagnosesAreComplete($plan);
    }

    protected function assertRoutineCareIsComplete(CarePlan $plan): void
    {
        $routineCares = $plan->routineCares()->get()->keyBy(
            fn (CarePlanRoutineCare $routineCare): string => $routineCare->item->value
        );

        foreach (RoutineCareItem::cases() as $item) {
            if ($item === RoutineCareItem::OTHER) {
                continue;
            }

            /** @var CarePlanRoutineCare|null $routineCare */
            $routineCare = $routineCares->get($item->value);

            if (! $routineCare || (! $routineCare->not_applicable && blank($routineCare->specification))) {
                throw new \InvalidArgumentException(__('Every routine care item must be specified or marked not applicable.'));
            }
        }
    }

    protected function assertNursingDiagnosesAreComplete(CarePlan $plan): void
    {
        $problems = $plan->problems()->with(['diagnoses.orders'])->get();

        foreach ($problems as $problem) {
            if ($problem->diagnoses->isEmpty()) {
                throw new \InvalidArgumentException(__('Every care plan problem requires a nursing diagnosis.'));
            }

            foreach ($problem->diagnoses as $diagnosis) {
                if (
                    ! $diagnosis->catalogue_id
                    || blank($diagnosis->problem_statement)
                    || blank($diagnosis->related_to)
                    || blank($diagnosis->as_evidenced_by)
                ) {
                    throw new \InvalidArgumentException(__('Every nursing diagnosis requires complete PES fields and a catalogue entry.'));
                }

                if ($diagnosis->orders->count() < 3) {
                    throw new \InvalidArgumentException(__('Every nursing diagnosis requires at least three orders.'));
                }
            }
        }
    }
}
