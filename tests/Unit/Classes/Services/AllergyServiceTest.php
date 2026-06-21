<?php

namespace Modules\Clinical\Tests\Unit\Classes\Services;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Models\Allergy;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class AllergyServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected AllergyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Clinical']);
        $this->service = app(AllergyService::class);
    }

    public function test_record_persists_allergy_for_patient(): void
    {
        $patient = Patient::factory()->create();

        $allergy = $this->service->record($patient, [
            'allergen_name' => 'Penicillin',
            'allergen_type' => AllergenType::MEDICATION->value,
            'reaction' => 'Rash',
            'severity' => AllergySeverity::MODERATE->value,
            'onset_type' => OnsetType::ACUTE->value,
        ]);

        $this->assertInstanceOf(Allergy::class, $allergy);
        $this->assertSame($patient->id, $allergy->patient_id);
        $this->assertSame('Penicillin', $allergy->allergen);
        $this->assertSame(AllergyVerificationStatus::UNVERIFIED, $allergy->verification_status);
    }

    public function test_get_active_for_patient_excludes_inactive_allergies(): void
    {
        $patient = Patient::factory()->create();

        $this->service->record($patient, [
            'allergen_name' => 'Peanuts',
            'allergen_type' => AllergenType::FOOD->value,
            'reaction' => 'Hives',
            'severity' => AllergySeverity::MILD->value,
            'onset_type' => OnsetType::CHRONIC->value,
        ]);

        $inactive = $this->service->record($patient, [
            'allergen_name' => 'Latex',
            'allergen_type' => AllergenType::ENVIRONMENTAL->value,
            'reaction' => 'Itching',
            'severity' => AllergySeverity::MILD->value,
            'onset_type' => OnsetType::ACUTE->value,
        ]);
        $inactive->deactivate();

        $active = $this->service->getActiveForPatient($patient->id);

        $this->assertCount(1, $active);
        $this->assertSame('Peanuts', $active->first()->allergen);
    }
}
