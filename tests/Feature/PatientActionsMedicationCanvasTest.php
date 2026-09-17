<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\MedicationCanvas;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['View Timeline', 'View MedicationCanvas', 'ViewAny Patient', 'View Patient'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }

    $this->actingAs($this->user);
});

it('hides the medication canvas link without an active encounter', function (): void {
    Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::FINISHED,
    ]);

    $action = PatientActions::make()->forPatient($this->patient)->medicationCanvasAction();

    expect($action->getName())->toBe('view_medication_canvas')
        ->and($action->isVisible())->toBeFalse();
});

it('links to the medication canvas when the patient has an active encounter', function (): void {
    Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    $action = PatientActions::make()->forPatient($this->patient)->medicationCanvasAction();

    expect($action->isVisible())->toBeTrue()
        ->and($action->getUrl())->toBe(MedicationCanvas::getUrl(['patient' => $this->patient->id]));

    Livewire::test(Timeline::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertActionExists('view_medication_canvas');
});
