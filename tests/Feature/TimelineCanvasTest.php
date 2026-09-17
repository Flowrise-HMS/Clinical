<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\CanvasLayoutService;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->clinician = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['View Timeline', 'ViewAny Patient', 'View Patient'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->clinician->givePermissionTo($permission);
    }

    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subDays(3),
        ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $encounter->id,
        'recorded_by' => $this->clinician->id,
        'recorded_at' => now()->subDays(2),
    ]);

    ClinicalNote::factory()->forPatient($this->patient)->create([
        'encounter_id' => $encounter->id,
        'author_id' => $this->clinician->id,
        'created_at' => now()->subDay(),
    ]);
});

function timelineCanvasLayout(): array
{
    return [
        'viewport' => ['x' => 10, 'y' => 20, 'zoom' => 0.75],
        'nodes' => ['encounter_1' => ['collapsed' => false, 'expanded' => true]],
        'notes' => [['id' => 'note_a', 'x' => 1, 'y' => 2, 'w' => 200, 'h' => 120, 'text' => 'Hi', 'color' => 'sky']],
        'edges' => [],
    ];
}

it('defaults to the list view and keeps the canvas payload empty', function (): void {
    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSet('displayMode', 'list')
        ->assertSet('canvasTree', null)
        ->assertSee('Canvas');
});

it('builds the patient hierarchy when switched to the canvas view', function (): void {
    $component = Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id])
        ->call('setView', 'canvas')
        ->assertSet('displayMode', 'canvas')
        ->assertSet('hasMoreEvents', false);

    $tree = $component->get('canvasTree');

    expect($tree['kind'])->toBe('patient')
        ->and($tree['id'])->toBe('patient_'.$this->patient->id)
        ->and($tree['title'])->toBe($this->patient->full_name);

    $encounters = collect($tree['children'])->where('kind', 'encounter')->values();

    expect($encounters)->toHaveCount(1)
        ->and($encounters[0]['defaultCollapsed'])->toBeFalse()
        ->and($encounters[0]['url'])->toBeString();

    $groups = collect($encounters[0]['children'])->keyBy('type');

    expect($groups->keys()->all())->toContain('vitals', 'note')
        ->and($groups['vitals']['kind'])->toBe('group')
        ->and($groups['vitals']['count'])->toBe(1)
        ->and($groups['vitals']['children'][0])->toHaveKeys(['id', 'kind', 'type', 'title', 'timeLabel', 'occurredAt'])
        ->and($groups['vitals']['children'][0]['type'])->toBe('vitals')
        ->and($groups['note']['children'][0]['type'])->toBe('note')
        ->and($component->get('canvasMeta'))->toHaveKeys(['now', 'timezone', 'encounterTotal', 'truncated'])
        ->and($component->get('savedLayout'))->toBeNull();
});

it('collapses older encounters by default', function (): void {
    Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subHour(),
        ]);

    $tree = Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->get('canvasTree');

    $encounters = collect($tree['children'])->where('kind', 'encounter')->values();

    expect($encounters)->toHaveCount(2)
        ->and($encounters[0]['isActive'])->toBeTrue()
        ->and($encounters[0]['defaultCollapsed'])->toBeFalse()
        ->and($encounters[1]['defaultCollapsed'])->toBeTrue();
});

it('normalises an unknown view back to the list', function (): void {
    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'bogus'])
        ->assertSet('displayMode', 'list');
});

it('saves the layout for the current user only', function (): void {
    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->call('saveLayout', timelineCanvasLayout())
        ->assertHasNoErrors()
        ->assertSet('savedLayout.viewport.zoom', 0.75);

    $this->assertDatabaseHas('clinical_canvas_layouts', [
        'user_id' => $this->clinician->id,
        'patient_id' => $this->patient->id,
        'canvas_key' => CanvasLayout::KEY_TIMELINE,
        'context_id' => null,
    ]);

    $other = User::factory()->create(['branch_id' => $this->branch->id]);
    foreach (['View Timeline', 'ViewAny Patient', 'View Patient'] as $permission) {
        $other->givePermissionTo($permission);
    }

    Livewire::actingAs($other)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->assertSet('savedLayout', null);

    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->assertSet('savedLayout.notes.0.text', 'Hi');
});

it('rejects an invalid layout without persisting it', function (): void {
    $layout = timelineCanvasLayout();
    $layout['notes'][0]['color'] = 'neon';

    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->call('saveLayout', $layout)
        ->assertNotified()
        ->assertSet('savedLayout', null);

    expect(CanvasLayout::query()->count())->toBe(0);
});

it('resets the saved layout', function (): void {
    app(CanvasLayoutService::class)->save(
        $this->clinician,
        CanvasLayout::KEY_TIMELINE,
        $this->patient->id,
        null,
        timelineCanvasLayout(),
    );

    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id, 'displayMode' => 'canvas'])
        ->assertSet('savedLayout.viewport.zoom', 0.75)
        ->call('resetLayout')
        ->assertSet('savedLayout', null);

    expect(CanvasLayout::query()->count())->toBe(0);
});
