<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment']);

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->clinician = User::factory()->create(['branch_id' => $this->branch->id]);
});

afterEach(function (): void {
    Model::preventLazyLoading(false);
});

it('builds every timeline event type while lazy loading is disabled', function (): void {
    $encounter = Encounter::factory()
        ->forPatient($this->patient)
        ->active()
        ->create([
            'branch_id' => $this->branch->id,
            'admitted_by' => $this->clinician->id,
        ]);

    VitalSign::factory()->forPatient($this->patient)->create([
        'encounter_id' => $encounter->id,
        'recorded_by' => $this->clinician->id,
    ]);

    ClinicalNote::factory()->forPatient($this->patient)->create([
        'encounter_id' => $encounter->id,
        'author_id' => $this->clinician->id,
    ]);

    $request = ServiceRequest::factory()->create([
        'patient_id' => $this->patient->id,
        'encounter_id' => $encounter->id,
        'branch_id' => $this->branch->id,
        'ordered_by' => $this->clinician->id,
    ]);

    RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => Service::factory()->create()->id,
    ]);

    $this->actingAs($this->clinician);

    Model::preventLazyLoading();

    $events = app(ClinicalWorkspaceService::class)
        ->setPatient(Patient::query()->findOrFail($this->patient->id))
        ->getTimelineEvents();

    expect($events->pluck('type')->all())->toContain('encounter', 'vitals', 'note', 'order')
        ->and($events->firstWhere('type', 'order')['description'])->toContain('1 item(s)');
});

it('reports the latest vitals while lazy loading is disabled', function (): void {
    $vital = VitalSign::factory()->forPatient($this->patient)->create([
        'recorded_by' => $this->clinician->id,
    ]);

    $this->actingAs($this->clinician);

    Model::preventLazyLoading();

    $latest = app(ClinicalWorkspaceService::class)
        ->setPatient(Patient::query()->findOrFail($this->patient->id))
        ->getLatestVitals();

    expect($latest?->id)->toBe($vital->id);
});
