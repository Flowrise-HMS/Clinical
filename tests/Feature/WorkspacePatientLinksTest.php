<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\CarePlanWorkspace;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );

    foreach ([
        'View ClinicalWorkspace',
        'View CarePlanWorkspace',
        'ViewAny CarePlan',
        'ViewAny Patient',
        'View Patient',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('renders clinical workspace search results as links to the patient', function (): void {
    $patientUrl = ClinicalWorkspace::getUrl(['patientId' => $this->patient->id]);

    Livewire::actingAs($this->user)
        ->test(ClinicalWorkspace::class)
        ->set('searchTerm', $this->patient->first_name)
        ->assertOk()
        ->assertSee('MRN: '.$this->patient->mrn)
        ->assertSeeHtml('href="'.e($patientUrl).'"')
        ->assertDontSeeHtml("wire:click=\"selectPatient('{$this->patient->id}')\"");
});

it('opens the clinical workspace on the patient from the url', function (): void {
    Livewire::actingAs($this->user)
        ->withQueryParams(['patientId' => $this->patient->id])
        ->test(ClinicalWorkspace::class)
        ->assertOk()
        ->assertSet('mode', 'patient')
        ->assertSet('patientId', $this->patient->id)
        ->assertSee($this->patient->full_name);
});

it('links critical patients on the workspace home to the patient', function (): void {
    Permission::findOrCreate('view_all_patients', 'web');
    $this->user->givePermissionTo('view_all_patients');

    Encounter::factory()
        ->forPatient($this->patient)
        ->emergency()
        ->withStatus(EncounterStatus::IN_PROGRESS)
        ->create(['branch_id' => $this->branch->id]);

    Livewire::actingAs($this->user)
        ->test(CriticalPatientsWidget::class)
        ->assertSee($this->patient->full_name)
        ->assertSeeHtml('href="'.e(ClinicalWorkspace::getUrl(['patientId' => $this->patient->id])).'"')
        ->assertDontSeeHtml('select-patient');
});

it('renders care plan workspace search results as links to the patient', function (): void {
    $patientUrl = CarePlanWorkspace::getUrl(['patientId' => $this->patient->id]);

    Livewire::actingAs($this->user)
        ->test(CarePlanWorkspace::class)
        ->set('searchTerm', $this->patient->first_name)
        ->assertOk()
        ->assertSeeHtml('href="'.e($patientUrl).'"')
        ->assertDontSeeHtml("wire:click=\"selectPatient('{$this->patient->id}')\"");
});

it('opens the care plan workspace on the patient from the url', function (): void {
    Livewire::actingAs($this->user)
        ->withQueryParams(['patientId' => $this->patient->id])
        ->test(CarePlanWorkspace::class)
        ->assertOk()
        ->assertSet('patientId', $this->patient->id)
        ->assertSee('MRN: '.$this->patient->mrn);
});
