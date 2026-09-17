<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\MedicationCanvas;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PrescriptionScheduleCalculator;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Nwidart\Modules\Facades\Module;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Billing', 'Clinical', 'Pharmacy']);

    config([
        'clinical.mar_payment.require_before_mar' => false,
        'clinical.mar_reminders.lead_minutes' => 15,
        'clinical.mar_reminders.grace_minutes' => 30,
    ]);

    Carbon::setTestNow('2026-09-17 07:30:00');

    $this->branch = Branch::factory()->default()->create();
    $this->patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $this->nurse = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['View MedicationCanvas', 'View Timeline', 'ViewAny Patient', 'View Patient', 'administer_medication'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $this->nurse->givePermissionTo($permission);
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function activeInpatientEncounter(Patient $patient, Branch $branch): Encounter
{
    return Encounter::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'type' => EncounterType::INPATIENT,
        'status' => EncounterStatus::IN_PROGRESS,
    ]);
}

/**
 * @return array{RequestItem, PrescriptionDetail}
 */
function seedCanvasOrder(TestCase $test, Encounter $encounter, MedicationFrequency $frequency, int $durationDays, string $administrationContext = 'in_facility', ?string $name = null): array
{
    $service = Service::factory()->create([
        'branch_id' => $encounter->branch_id,
        'category_id' => (fn () => $this->medicationServiceCategory())->call($test)->id,
        'name' => $name ?? fake()->unique()->word().' 500mg',
        'requires_payment_before' => false,
    ]);
    Medication::factory()->create(['service_id' => $service->id]);

    $request = ServiceRequest::factory()->create([
        'patient_id' => $encounter->patient_id,
        'encounter_id' => $encounter->id,
        'branch_id' => $encounter->branch_id,
    ]);

    $item = RequestItem::factory()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'status' => 'pending',
    ]);

    $detail = PrescriptionDetail::create([
        'request_item_id' => $item->id,
        'frequency' => $frequency->value,
        'duration_days' => $durationDays,
        'route' => 'po',
        'dose_amount' => 1,
        'administration_context' => $administrationContext === 'in_facility'
            ? AdministrationContext::IN_FACILITY
            : AdministrationContext::TAKE_HOME,
        'course_started_at' => now(),
        'course_end_at' => now()->addDays($durationDays),
        'total_administrations' => app(PrescriptionScheduleCalculator::class)->compute([
            'frequency' => $frequency->value,
            'duration_days' => $durationDays,
            'prn' => false,
            'course_started_at' => now(),
        ])['total_administrations'],
    ]);

    return [$item, $detail];
}

it('explains when the patient has no active encounter', function (): void {
    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSet('emptyReason', 'no_active_encounter')
        ->assertSee('No active encounter');
});

it('explains when the active encounter has no in-facility medications', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2, 'take_home');

    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSet('emptyReason', 'no_in_facility_meds')
        ->assertSet('activeEncounterId', $encounter->id);
});

it('builds one lane per in-facility prescription with scheduled slots', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    [$item, $detail] = seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    // BID default times are 08:00 and 20:00; at 07:50 the first slot is inside the 15-minute lead window.
    Carbon::setTestNow('2026-09-17 07:50:00');

    $component = Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->assertOk()
        ->assertSet('emptyReason', '');

    $tree = $component->get('canvasTree');
    $nodes = $tree['children'];
    $meta = $component->get('canvasMeta');

    expect($tree['kind'])->toBe('encounter')
        ->and($tree['id'])->toBe('encounter_'.$encounter->id)
        ->and($nodes)->toHaveCount(1)
        ->and($nodes[0]['kind'])->toBe('medication')
        ->and($nodes[0]['childLayout'])->toBe('grid')
        ->and($nodes[0]['children'])->toHaveCount($detail->total_administrations)
        ->and($nodes[0]['children'][0]['kind'])->toBe('dose')
        ->and($nodes[0]['children'][0]['isNextDue'])->toBeTrue()
        ->and($nodes[0]['id'])->toBe('rx_'.$item->id)
        ->and($nodes[0]['name'])->toBe(Service::withoutGlobalScopes()->find($item->service_id)->name)
        ->and($nodes[0]['frequency'])->toBe('bid')
        ->and($nodes[0]['frequencyLabel'])->toBe(MedicationFrequency::BID->getLabel())
        ->and($nodes[0]['slots'])->toHaveCount($detail->total_administrations)
        ->and($nodes[0]['nextDueSequence'])->toBe(1)
        ->and($nodes[0]['canRecord'])->toBeTrue()
        ->and($nodes[0]['givenCount'])->toBe(0)
        ->and($nodes[0]['totalAdministrations'])->toBe(4)
        ->and($meta)->toHaveKeys(['now', 'timezone', 'window', 'encounterId'])
        ->and($meta['encounterId'])->toBe($encounter->id);

    $slotsBySequence = collect($nodes[0]['slots'])->keyBy('sequence');

    expect($slotsBySequence[1]['dueAtLabel'])->toBe('08:00')
        ->and($slotsBySequence[1]['status'])->toBe('due_soon')
        ->and($slotsBySequence[1]['actionable'])->toBeTrue()
        ->and($slotsBySequence[2]['status'])->toBe('upcoming')
        ->and($slotsBySequence[2]['actionable'])->toBeFalse();
});

it('marks a missed slot overdue and moves the actionable slot forward after a dose is given', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    [$item] = seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    Carbon::setTestNow('2026-09-17 09:15:00');

    $component = Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id]);

    $slots = collect($component->get('canvasTree')['children'][0]['slots'])->keyBy('sequence');

    expect($slots[1]['status'])->toBe('overdue')
        ->and($slots[1]['actionable'])->toBeTrue()
        ->and($component->get('canvasTree')['children'][0]['nextDueStatus'])->toBe('overdue');

    app(MedicationAdministrationService::class)->administer($item->fresh(), [
        'status' => MedicationAdministrationStatus::GIVEN->value,
        'quantity_given' => 1,
        'started_at' => now(),
    ], null, $this->nurse);

    $component->call('refreshCanvas');
    $node = $component->get('canvasTree')['children'][0];
    $slots = collect($node['slots'])->keyBy('sequence');

    expect($slots[1]['status'])->toBe('given')
        ->and($slots[1]['administration']['by'])->toBe($this->nurse->name)
        ->and($slots[1]['actionable'])->toBeFalse()
        ->and($node['nextDueSequence'])->toBe(2)
        ->and($slots[2]['actionable'])->toBeTrue()
        ->and($node['givenCount'])->toBe(1);
});

it('records a dose through the canvas action', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    [$item] = seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->callAction('recordDose', data: [
            'status' => MedicationAdministrationStatus::GIVEN->value,
            'quantity_given' => 1,
            'started_at' => now()->toDateTimeString(),
        ], arguments: ['requestItemId' => $item->id])
        ->assertHasNoActionErrors()
        ->assertNotified('Dose recorded')
        ->assertSet('canvasTree.children.0.givenCount', 1)
        ->assertSet('canvasTree.children.0.nextDueSequence', 2);

    expect(MedicationAdministration::query()->where('request_item_id', $item->id)->count())->toBe(1);
});

it('refuses to mount the record action for an item outside the active encounter', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    $otherPatient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id])
    );
    $otherEncounter = activeInpatientEncounter($otherPatient, $this->branch);
    [$foreignItem] = seedCanvasOrder($this, $otherEncounter, MedicationFrequency::BID, 2);

    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->mountAction('recordDose', arguments: ['requestItemId' => $foreignItem->id])
        ->assertActionNotMounted('recordDose');

    expect(MedicationAdministration::query()->where('request_item_id', $foreignItem->id)->count())->toBe(0);
});

it('scopes the saved layout to the active encounter', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    Livewire::actingAs($this->nurse)
        ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
        ->call('saveLayout', [
            'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
            'nodes' => [],
            'notes' => [],
            'edges' => [],
        ])
        ->assertSet('savedLayout.viewport.zoom', 1.0);

    $this->assertDatabaseHas('clinical_canvas_layouts', [
        'user_id' => $this->nurse->id,
        'patient_id' => $this->patient->id,
        'canvas_key' => CanvasLayout::KEY_MEDICATIONS,
        'context_id' => $encounter->id,
    ]);
});

it('shows an empty state when the Pharmacy module is disabled', function (): void {
    $encounter = activeInpatientEncounter($this->patient, $this->branch);
    seedCanvasOrder($this, $encounter, MedicationFrequency::BID, 2);

    $module = Module::find('Pharmacy');
    expect($module)->not->toBeNull();

    try {
        $module->disable();

        Livewire::actingAs($this->nurse)
            ->test(MedicationCanvas::class, ['patientId' => $this->patient->id])
            ->assertOk()
            ->assertSet('emptyReason', 'pharmacy_disabled')
            ->assertSet('canvasTree', null);
    } finally {
        $module->enable();
    }
});
