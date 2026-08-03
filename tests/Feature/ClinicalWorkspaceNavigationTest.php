<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\PatientProfile;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    foreach ([
        'View ClinicalWorkspace',
        'View PatientProfile',
        'View Timeline',
        'ViewAny Patient',
        'View Patient',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('opens clinical workspace from PatientActions with the selected patient', function (): void {
    $this->actingAs($this->user);

    $action = PatientActions::make()
        ->forPatient($this->patient)
        ->clinicalWorkspaceAction();

    expect($action->getName())->toBe('open_clinical_workspace')
        ->and($action->getLabel())->toBe('Clinical Workspace')
        ->and($action->isVisible())->toBeTrue()
        ->and($action->getUrl())->toBe(ClinicalWorkspace::getUrl([
            'patientId' => $this->patient->id,
        ]));
});

it('exposes a clinical workspace header action on the patient profile', function (): void {
    Livewire::actingAs($this->user)
        ->test(PatientProfile::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertActionExists('open_clinical_workspace')
        ->assertActionHasUrl(
            'open_clinical_workspace',
            ClinicalWorkspace::getUrl([
                'patientId' => $this->patient->id,
            ])
        );
});

it('exposes a clinical workspace header action on the timeline', function (): void {
    Livewire::actingAs($this->user)
        ->test(Timeline::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertActionExists('open_clinical_workspace')
        ->assertActionHasUrl(
            'open_clinical_workspace',
            ClinicalWorkspace::getUrl([
                'patientId' => $this->patient->id,
            ])
        );
});
