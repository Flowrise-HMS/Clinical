<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Settings\ClinicalSettings;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * Clinical settings that used to be shadowed by config() or never read.
 */
class ClinicalSettingsEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical']);
    }

    public function test_new_encounters_use_the_default_class_and_status_settings(): void
    {
        ClinicalSettings::fake([
            'default_encounter_class' => 'urgent',
            'default_encounter_status' => 'arrived',
            'default_encounter_type' => 'emergency',
        ]);

        $branch = Branch::factory()->default()->create();
        $patient = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $branch->id]));
        $encounter = app(EncounterService::class)->createForPatient($patient, EncounterType::OUTPATIENT, createdBy: User::factory()->create()->id);

        $this->assertSame(EncounterPriority::URGENT, $encounter->priority);
        $this->assertSame(EncounterStatus::ARRIVED, EncounterForm::defaultStatus());
        $this->assertSame(['planned', 'arrived'], array_keys(EncounterForm::statusOptions(null)));
    }

    public function test_invalid_default_status_falls_back_to_planned(): void
    {
        ClinicalSettings::fake(['default_encounter_status' => 'finished']);

        $this->assertSame(EncounterStatus::PLANNED, EncounterForm::defaultStatus());
    }

    public function test_mar_schedule_values_are_read_from_settings_before_config(): void
    {
        ClinicalSettings::fake(['mar_reminders_lead_minutes' => 99]);
        config(['clinical.mar_reminders.lead_minutes' => 15]);

        $this->assertSame(99, app_settings()->clinicalValue('mar_reminders_lead_minutes', config('clinical.mar_reminders.lead_minutes')));
    }
}
