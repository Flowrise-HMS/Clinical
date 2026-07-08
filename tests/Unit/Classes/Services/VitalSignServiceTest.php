<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\VitalSignService;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class VitalSignServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected VitalSignService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);
        $this->service = app(VitalSignService::class);
    }

    public function test_record_persists_vital_sign_for_patient(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $user = User::factory()->create();
        $vital = $this->service->record($patient, [
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'heart_rate' => 72,
        ], type: VitalSignType::ROUTINE, recordedBy: $user->id);

        $this->assertInstanceOf(VitalSign::class, $vital);
        $this->assertSame($patient->id, $vital->patient_id);
        $this->assertSame(120, $vital->systolic_bp);
        $this->assertSame(VitalSignType::ROUTINE, $vital->type);
    }

    public function test_record_persists_encounter_id_from_parameter(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();

        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
        ]);

        $vital = $this->service->record($patient, [
            'systolic_bp' => 118,
            'diastolic_bp' => 76,
        ], encounterId: $encounter->id, recordedBy: $user->id);

        $this->assertSame($encounter->id, $vital->encounter_id);
    }

    public function test_get_latest_returns_most_recent_vital_sign(): void
    {
        $branch = Branch::factory()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);

        $user = User::factory()->create();

        $this->service->record($patient, [
            'systolic_bp' => 110,
            'recorded_at' => now()->subHour(),
        ], recordedBy: $user->id);
        $newer = $this->service->record($patient, [
            'systolic_bp' => 130,
            'recorded_at' => now(),
        ], recordedBy: $user->id);

        $latest = $this->service->getLatest($patient);

        $this->assertNotNull($latest);
        $this->assertSame($newer->id, $latest->id);
    }

    public function test_get_trend_rejects_invalid_vital_type(): void
    {
        $patient = Patient::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vital type');

        $this->service->getTrend($patient, 'invalid_metric');
    }
}
