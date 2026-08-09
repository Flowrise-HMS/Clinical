<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline;
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

    foreach ([
        'View Timeline',
        'ViewAny Patient',
        'View Patient',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->clinician->givePermissionTo($permission);
    }
});

it('returns patient-wide timeline counts even when an encounter is set on the service', function (): void {
    $active = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subHour(),
        ]);

    $prior = Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subDays(3),
        ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $active->id,
        'recorded_by' => $this->clinician->id,
        'recorded_at' => now()->subMinutes(30),
    ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $prior->id,
        'recorded_by' => $this->clinician->id,
        'recorded_at' => now()->subDays(2),
    ]);

    ClinicalNote::factory()->forPatient($this->patient)->create([
        'encounter_id' => $prior->id,
        'author_id' => $this->clinician->id,
        'created_at' => now()->subDays(2)->addHour(),
    ]);

    $this->actingAs($this->clinician);

    $service = app(ClinicalWorkspaceService::class)
        ->setPatient($this->patient);

    $patientWideCounts = $service->getTimelineEventCounts();

    expect($patientWideCounts['encounter'])->toBe(2)
        ->and($patientWideCounts['vitals'])->toBe(2)
        ->and($patientWideCounts['note'])->toBe(1)
        ->and($patientWideCounts['all'])->toBe(5);

    $scopedCounts = $service->setEncounter($active)->getTimelineEventCounts();

    expect($scopedCounts['encounter'])->toBe(1)
        ->and($scopedCounts['vitals'])->toBe(1)
        ->and($scopedCounts['note'])->toBe(0)
        ->and($scopedCounts['all'])->toBe(2);
});

it('merges patient-wide events chronologically and paginates the merged stream', function (): void {
    $active = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subHour(),
        ]);

    $prior = Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subDays(5),
        ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $prior->id,
        'recorded_by' => $this->clinician->id,
        'recorded_at' => now()->subDays(4),
    ]);

    ClinicalNote::factory()->forPatient($this->patient)->create([
        'encounter_id' => $active->id,
        'author_id' => $this->clinician->id,
        'created_at' => now()->subMinutes(20),
    ]);

    $this->actingAs($this->clinician);

    $service = app(ClinicalWorkspaceService::class)
        ->setPatient($this->patient)
        ->clearEncounter();

    $page = $service->getTimelineEvents(2);

    expect($page)->toHaveCount(2)
        ->and($page->pluck('type')->all())->toBe(['note', 'encounter']);

    $secondPage = $service->getTimelineEvents(2, null, 2);

    expect($secondPage)->toHaveCount(2)
        ->and($secondPage->pluck('type')->all())->toBe(['vitals', 'encounter']);

    $withActiveEncounterSet = app(ClinicalWorkspaceService::class)
        ->setPatient($this->patient)
        ->clearEncounter()
        ->getTimelineEvents(50);

    expect($withActiveEncounterSet->pluck('type')->all())
        ->toContain('encounter', 'vitals', 'note')
        ->and($withActiveEncounterSet)->toHaveCount(4);
});

it('loads the timeline page patient-wide and switches filters without encounter scope', function (): void {
    $active = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subHour(),
        ]);

    Encounter::factory()
        ->forPatient($this->patient)
        ->finished()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
            'admitted_at' => now()->subDays(2),
        ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $active->id,
        'recorded_by' => $this->clinician->id,
    ]);

    Livewire::actingAs($this->clinician)
        ->test(Timeline::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSet('hasMoreEvents', false)
        ->tap(function ($component): void {
            $events = $component->instance()->getTimelineEvents();
            $counts = $component->instance()->getEventCounts();

            expect($events)->toHaveCount(3)
                ->and($counts['encounter'])->toBe(2)
                ->and($counts['vitals'])->toBe(1)
                ->and($counts['all'])->toBe(3);
        })
        ->call('setFilter', 'vitals')
        ->assertSet('activeFilter', 'vitals')
        ->tap(function ($component): void {
            $events = $component->instance()->getTimelineEvents();

            expect($events)->toHaveCount(1)
                ->and($events->first()['type'])->toBe('vitals');
        });
});
