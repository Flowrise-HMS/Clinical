<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Tables\ClinicalNotesTable;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables\VitalSignsTable;
use Modules\Clinical\Filament\Widgets\PatientNotesWidget;
use Modules\Clinical\Filament\Widgets\PatientVitalsHistoryWidget;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\VitalSign;
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
});

it('lists clinical notes for the selected patient in the notes widget', function (): void {
    $this->actingAs($this->user);

    $note = ClinicalNote::factory()->forPatient($this->patient)->create([
        'author_id' => $this->user->id,
        'note_type' => NoteType::PROGRESS,
        'status' => NoteStatus::DRAFT,
        'subject' => 'Ward progress note',
        'content' => ['text' => '<p>Patient stable overnight.</p>'],
    ]);

    ClinicalNote::factory()->create([
        'author_id' => $this->user->id,
        'subject' => 'Other patient note',
        'content' => ['text' => '<p>Should not appear.</p>'],
    ]);

    Livewire::test(PatientNotesWidget::class, [
        'patientId' => $this->patient->id,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$note])
        ->assertSee('Ward progress note')
        ->assertDontSee('Other patient note');

    $columnNames = collect(ClinicalNotesTable::columns())->map->getName()->all();

    expect($columnNames)->toContain('note_type', 'subject', 'author.name', 'status', 'is_signed', 'created_at');
});

it('reuses vital signs table columns in the vitals history widget', function (): void {
    $this->actingAs($this->user);

    $vital = VitalSign::factory()->forPatient($this->patient)->create([
        'recorded_by' => $this->user->id,
        'heart_rate' => 88,
        'branch_id' => $this->branch->id,
    ]);

    Livewire::test(PatientVitalsHistoryWidget::class, [
        'patientId' => $this->patient->id,
    ])
        ->loadTable()
        ->assertCanSeeTableRecords([$vital])
        ->assertSee('88');

    $columnNames = collect(VitalSignsTable::columns())->map->getName()->all();

    expect($columnNames)->toContain('recorded_at', 'heart_rate', 'spo2', 'recordedBy.name');
});

it('hides vitals history record actions from users without permission', function (): void {
    $vital = VitalSign::factory()->forPatient($this->patient)->create([
        'recorded_by' => $this->user->id,
        'heart_rate' => 88,
        'branch_id' => $this->branch->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(PatientVitalsHistoryWidget::class, [
            'patientId' => $this->patient->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$vital])
        ->assertTableActionHidden('view', $vital)
        ->assertTableActionHidden('edit', $vital)
        ->assertTableActionHidden('delete', $vital);
});

it('shows vitals history record actions for users with permission', function (): void {
    Permission::findOrCreate('View VitalSign', 'web');
    Permission::findOrCreate('Update VitalSign', 'web');
    Permission::findOrCreate('Delete VitalSign', 'web');
    $this->user->givePermissionTo(['View VitalSign', 'Update VitalSign', 'Delete VitalSign']);

    $vital = VitalSign::factory()->forPatient($this->patient)->create([
        'recorded_by' => $this->user->id,
        'heart_rate' => 88,
        'branch_id' => $this->branch->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(PatientVitalsHistoryWidget::class, [
            'patientId' => $this->patient->id,
        ])
        ->assertOk()
        ->assertCanSeeTableRecords([$vital])
        ->assertTableActionVisible('view', $vital)
        ->assertTableActionVisible('edit', $vital)
        ->assertTableActionVisible('delete', $vital);
});
