<?php

namespace Modules\Clinical\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Appointment\Models\Appointment;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\EncounterService;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\CreateEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Pages\EditEncounter;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\EncounterForm;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * Planned encounters had no path to ARRIVED outside inpatient admission, so
 * outpatient visits created in the workspace could never be completed.
 */
class EncounterArriveActionTest extends TestCase
{
    use DatabaseTransactions;

    private Branch $branch;

    private User $user;

    private Patient $patient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff', 'Appointment']);
        Gate::before(fn () => true);

        $this->branch = Branch::factory()->default()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->patient = Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $this->branch->id]));
        $this->actingAs($this->user);
        session(['current_branch_id' => $this->branch->id]);
    }

    public function test_arrive_action_moves_a_planned_encounter_to_arrived_and_unlocks_complete(): void
    {
        $encounter = $this->plannedEncounter();

        $this->assertTrue(EncounterActions::isArriveVisible($encounter));
        $this->assertFalse(EncounterActions::isCompleteVisible($encounter));

        Livewire::test(EditEncounter::class, ['record' => $encounter->getRouteKey()])
            ->assertActionVisible('arrive')
            ->callAction('arrive')
            ->assertNotified('Patient marked as arrived');

        $encounter->refresh();

        $this->assertSame(EncounterStatus::ARRIVED, $encounter->status);
        $this->assertNotNull($encounter->admitted_at);
        $this->assertTrue(EncounterActions::isCompleteVisible($encounter));
        $this->assertFalse(EncounterActions::isArriveVisible($encounter));
    }

    public function test_status_select_only_offers_legal_transitions(): void
    {
        $planned = $this->plannedEncounter();

        $this->assertSame(
            ['planned', 'arrived', 'cancelled'],
            array_keys(EncounterForm::statusOptions($planned)),
        );

        Livewire::test(EditEncounter::class, ['record' => $planned->getRouteKey()])
            ->fillForm(['status' => EncounterStatus::FINISHED->value])
            ->call('save')
            ->assertHasFormErrors(['status']);

        $this->assertSame(EncounterStatus::PLANNED, $planned->fresh()->status);
    }

    public function test_appointment_check_in_arrives_the_planned_encounter(): void
    {
        $encounter = $this->plannedEncounter();
        $appointment = Appointment::factory()->create([
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'status' => AppointmentStatus::BOOKED,
            'start_at' => now()->setTime(10, 0),
            'end_at' => now()->setTime(10, 30),
        ]);

        Livewire::test(WorkspaceTodayAppointmentsWidget::class)
            ->callAction('checkIn', arguments: ['appointment' => $appointment->id])
            ->assertNotified('Patient checked in');

        $this->assertSame(AppointmentStatus::ARRIVED, $appointment->fresh()->status);
        $this->assertSame(EncounterStatus::ARRIVED, $encounter->fresh()->status);
    }

    public function test_encounters_list_create_page_stamps_the_creator(): void
    {
        Livewire::test(CreateEncounter::class)
            ->fillForm([
                'patient_id' => $this->patient->id,
                'branch_id' => $this->branch->id,
                'type' => 'outpatient',
                'priority' => 'routine',
                'status' => EncounterStatus::PLANNED->value,
                'coverage_type' => 'none',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $encounter = Encounter::query()->where('patient_id', $this->patient->id)->latest('created_at')->first();

        $this->assertNotNull($encounter);
        $this->assertSame($this->user->id, $encounter->created_by);
        $this->assertSame(EncounterStatus::PLANNED, $encounter->status);
    }

    public function test_arrive_refuses_encounters_that_are_not_planned(): void
    {
        $arrived = Encounter::factory()->forPatient($this->patient)->outpatient()->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::ARRIVED,
            'bed_id' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(EncounterService::class)->arrive($arrived);
    }

    private function plannedEncounter(): Encounter
    {
        return Encounter::factory()->forPatient($this->patient)->outpatient()->create([
            'branch_id' => $this->branch->id,
            'status' => EncounterStatus::PLANNED,
            'bed_id' => null,
            'created_by' => $this->user->id,
        ]);
    }
}
