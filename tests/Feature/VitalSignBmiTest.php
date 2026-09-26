<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->nurse->assignRole(Role::findOrCreate('nurse', 'web'));

    foreach (['View ClinicalWorkspace', 'Create VitalSign', 'View VitalSign', 'ViewAny Patient', 'View Patient'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->nurse->givePermissionTo($permission);
    }

    $this->patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id]));
});

it('calculates BMI in kg/m² from kilograms and centimetres', function (): void {
    expect(VitalSign::calculateBmi(70, 170))->toBe(24.22)
        ->and(VitalSign::calculateBmi('62', '153'))->toBe(26.49)
        ->and(VitalSign::calculateBmi(70, null))->toBeNull()
        ->and(VitalSign::calculateBmi(null, 170))->toBeNull()
        ->and(VitalSign::calculateBmi(70, 0))->toBeNull()
        ->and(VitalSign::bmiCategoryFor(24.22))->toBe('Normal')
        ->and(VitalSign::bmiCategoryFor(18.4))->toBe('Underweight');
});

it('recalculates the stored BMI when a measurement is corrected', function (): void {
    $this->actingAs($this->nurse);

    $vitals = VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'weight' => 70,
        'height' => 170,
    ]);

    expect((float) $vitals->bmi)->toBe(24.22);

    $vitals->update(['weight' => 85]);
    expect((float) $vitals->fresh()->bmi)->toBe(29.41);

    $vitals->update(['height' => null]);
    expect($vitals->fresh()->bmi)->toBeNull();
});

it('updates the BMI field live when weight or height changes', function (): void {
    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'vitals')
        ->assertDontSee('BMI (calculated)')
        ->set('vitalsData.height', 170)
        ->assertDontSee('BMI (calculated)')
        ->set('vitalsData.weight', 70)
        ->assertSee('BMI (calculated)')
        ->assertSeeHtml('kg/m²')
        ->assertSet('vitalsData.calculated_bmi', 24.22)
        ->set('vitalsData.weight', 85)
        ->assertSet('vitalsData.calculated_bmi', 29.41)
        ->set('vitalsData.height', null)
        ->assertSet('vitalsData.calculated_bmi', null)
        ->assertDontSee('BMI (calculated)');
});

it('rejects a height entered in metres instead of centimetres', function (): void {
    Livewire::actingAs($this->nurse)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'vitals')
        ->set('vitalsData.systolic_bp', 120)
        ->set('vitalsData.diastolic_bp', 80)
        ->set('vitalsData.weight', 70)
        ->set('vitalsData.height', 1.7)
        ->call('saveVitals')
        ->assertHasErrors(['vitalsData.height']);

    expect(VitalSign::query()->where('patient_id', $this->patient->id)->exists())->toBeFalse();
});
