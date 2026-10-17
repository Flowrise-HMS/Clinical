<?php

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Livewire\Livewire;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\WardBoard;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Enums\SidebarGroup;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
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
        'View WardBoard',
        'ViewAny Patient',
        'View Patient',
        'View Encounter',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }
});

it('configures WardBoard under Workspaces navigation group without cluster', function (): void {
    expect(WardBoard::getCluster())->toBeNull()
        ->and(WardBoard::getNavigationGroup())->toBe(SidebarGroup::Workspaces)
        ->and(WardBoard::getNavigationSort())->toBe(15)
        ->and(WardBoard::getSlug())->toBe('wards');
});

it('provides link to Ward Board on clinical workspace home and in header actions', function (): void {
    $this->actingAs($this->user);

    Livewire::actingAs($this->user)
        ->test(ClinicalWorkspace::class)
        ->assertOk()
        ->assertSeeHtml(WardBoard::getUrl())
        ->assertActionExists('ward_board');

    Livewire::actingAs($this->user)
        ->withQueryParams(['patientId' => $this->patient->id])
        ->test(ClinicalWorkspace::class)
        ->assertOk()
        ->assertActionExists('view_ward_board');
});

it('places triage tab immediately after vitals tab on clinical workspace', function (): void {
    $component = app(ClinicalWorkspace::class);

    $clinicianTabs = array_keys($component->getClinicianTabs());
    $vitalsIndex = array_search('vitals', $clinicianTabs);
    $triageIndex = array_search('triage', $clinicianTabs);

    expect($vitalsIndex)->not->toBeFalse()
        ->and($triageIndex)->toBe($vitalsIndex + 1);

    $nurseTabs = array_keys($component->getNurseTabs());
    $nurseVitalsIndex = array_search('vitals', $nurseTabs);
    $nurseTriageIndex = array_search('triage', $nurseTabs);

    expect($nurseVitalsIndex)->not->toBeFalse()
        ->and($nurseTriageIndex)->toBe($nurseVitalsIndex + 1);
});

it('includes recorded_at in VitalSignForm with native datetime picker', function (): void {
    $workspace = Livewire::actingAs($this->user)->test(ClinicalWorkspace::class)->instance();
    $form = $workspace->getSchema('vitalsForm');

    $recordedAt = $form->getComponent('recorded_at');

    expect($recordedAt)->not->toBeNull()
        ->and($recordedAt instanceof DateTimePicker)->toBeTrue()
        ->and($recordedAt->isNative())->toBeTrue();
});

it('provides helper text on clinical note form fields', function (): void {
    $workspace = Livewire::actingAs($this->user)->test(ClinicalWorkspace::class)->instance();
    $form = $workspace->getSchema('noteForm');

    $subjectField = $form->getComponent('subject');
    $contentField = $form->getComponent('content');
    $noteTypeField = $form->getComponent('note_type');
    $statusField = $form->getComponent('status');

    $subjectHelper = $subjectField?->getChildSchema(Filament\Forms\Components\Field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0]?->getContent();
    $contentHelper = $contentField?->getChildSchema(Filament\Forms\Components\Field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0]?->getContent();
    $noteTypeHelper = $noteTypeField?->getChildSchema(Filament\Forms\Components\Field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0]?->getContent();
    $statusHelper = $statusField?->getChildSchema(Filament\Forms\Components\Field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0]?->getContent();

    expect($subjectHelper)->not->toBeNull()
        ->and($contentHelper)->not->toBeNull()
        ->and($noteTypeHelper)->not->toBeNull()
        ->and($statusHelper)->not->toBeNull();
});

it('renders patients table and clickable bed cards on Ward Board', function (): void {
    $ward = Location::factory()->room()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Female Ward',
        'is_active' => true,
    ]);

    $bed = Location::factory()->bed()->create([
        'branch_id' => $this->branch->id,
        'parent_id' => $ward->id,
        'name' => 'Bed F1',
        'is_active' => true,
    ]);

    $encounter = Encounter::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'location_id' => $ward->id,
        'bed_id' => $bed->id,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);

    Livewire::actingAs($this->user)
        ->test(WardBoard::class, ['wardId' => $ward->id])
        ->assertOk()
        ->assertSeeHtml('href="'.e(ClinicalWorkspace::getUrl(['patientId' => $this->patient->id])).'"')
        ->assertSee('Workspace')
        ->assertSee($this->patient->full_name);
});
