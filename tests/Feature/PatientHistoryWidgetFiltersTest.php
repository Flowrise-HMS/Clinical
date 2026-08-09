<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Tables\ClinicalNotesTable;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables\EncounterDiagnosesTable;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Tables\RequestItemsTable;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\PatientDiagnosesWidget;
use Modules\Clinical\Filament\Widgets\PatientNotesWidget;
use Modules\Clinical\Filament\Widgets\PatientOrdersWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
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

    $this->actingAs($this->user);
});

it('exposes a date range filter on every history widget table', function (): void {
    $filterNames = fn (array $filters): array => collect($filters)->map->getName()->all();

    expect($filterNames(ClinicalNotesTable::filters()))
        ->toContain('note_type', 'status', 'author', 'created_at')
        ->and($filterNames(EncounterDiagnosesTable::filters()))
        ->toContain('type', 'certainty', 'created_at')
        ->and($filterNames(RequestItemsTable::filters()))
        ->toContain('status', 'category', 'created_at');
});

it('narrows clinical notes to the selected date range', function (): void {
    $recent = ClinicalNote::factory()->forPatient($this->patient)->create([
        'author_id' => $this->user->id,
        'subject' => 'Recent progress note',
        'created_at' => now()->subDay(),
    ]);

    $old = ClinicalNote::factory()->forPatient($this->patient)->create([
        'author_id' => $this->user->id,
        'subject' => 'Last year note',
        'created_at' => now()->subYear(),
    ]);

    Livewire::test(PatientNotesWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->assertCanSeeTableRecords([$recent, $old])
        ->filterTable('created_at', [
            'created_at_from' => now()->subWeek()->toDateTimeString(),
            'created_at_until' => now()->toDateTimeString(),
        ])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('narrows diagnoses by type and by date range', function (): void {
    $recent = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'ordered_by' => $this->user->id,
        'type' => DiagnosisType::Primary,
        'created_at' => now()->subDay(),
    ]);

    $old = EncounterDiagnosis::factory()->create([
        'patient_id' => $this->patient->id,
        'ordered_by' => $this->user->id,
        'type' => DiagnosisType::Primary,
        'created_at' => now()->subYear(),
    ]);

    Livewire::test(PatientDiagnosesWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->assertCanSeeTableRecords([$recent, $old])
        ->filterTable('created_at', [
            'created_at_from' => now()->subWeek()->toDateTimeString(),
            'created_at_until' => now()->toDateTimeString(),
        ])
        ->assertCanSeeTableRecords([$recent])
        ->assertCanNotSeeTableRecords([$old]);
});

it('narrows patient orders by status', function (): void {
    $serviceRequest = ServiceRequest::factory()->forPatient($this->patient)->create();

    $completed = RequestItem::factory()->forRequest($serviceRequest)->completed()->create();
    $pending = RequestItem::factory()->forRequest($serviceRequest)->pending()->create();

    Livewire::test(PatientOrdersWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->assertCanSeeTableRecords([$completed, $pending])
        ->filterTable('status', RequestItemStatus::COMPLETED->value)
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$pending]);
});

it('reaches orders beyond the twenty most recent when a date range is applied', function (): void {
    $serviceRequest = ServiceRequest::factory()->forPatient($this->patient)->create();

    $oldest = RequestItem::factory()->forRequest($serviceRequest)->create([
        'created_at' => now()->subYear(),
    ]);

    RequestItem::factory()
        ->count(25)
        ->forRequest($serviceRequest)
        ->create(['created_at' => now()->subDay()]);

    Livewire::test(PatientOrdersWidget::class, ['patientId' => $this->patient->id])
        ->loadTable()
        ->filterTable('created_at', [
            'created_at_from' => now()->subYears(2)->toDateTimeString(),
            'created_at_until' => now()->subMonth()->toDateTimeString(),
        ])
        ->assertCanSeeTableRecords([$oldest]);
});

it('shows no orders when no patient is in context', function (): void {
    $serviceRequest = ServiceRequest::factory()->forPatient($this->patient)->create();
    $item = RequestItem::factory()->forRequest($serviceRequest)->create();

    Livewire::test(PatientOrdersWidget::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$item]);
});

it('mounts every history widget for a user holding no clinical permissions', function (): void {
    foreach ([PatientOrdersWidget::class, PatientDiagnosesWidget::class, PatientNotesWidget::class] as $widget) {
        Livewire::test($widget, ['patientId' => $this->patient->id])
            ->assertOk()
            ->loadTable()
            ->assertOk();
    }
});

it('switches away from the history tab without an authorization failure', function (): void {
    foreach (['View ClinicalWorkspace', 'ViewAny Patient', 'View Patient'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->user->givePermissionTo($permission);
    }

    Encounter::factory()->create([
        'patient_id' => $this->patient->id,
        'branch_id' => $this->branch->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->set('activeTab', 'history')
        ->assertOk()
        ->set('activeTab', 'notes')
        ->assertOk()
        ->set('activeTab', 'encounter')
        ->assertOk();
});
