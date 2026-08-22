<?php

use App\Models\User;
use Filament\Actions\Action;
use Livewire\Livewire;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Appointment\Models\Appointment;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Modules\Staff\Models\Staff;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Staff', 'Appointment']);

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

it('lists todays branch appointments on the workspace home widget', function (): void {
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    $staff = Staff::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $this->user->id]);

    $today = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'practitioner_primary_id' => $staff->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->setTime(9, 0),
        'end_at' => now()->setTime(9, 30),
    ]);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->addDay()->setTime(9, 0),
        'end_at' => now()->addDay()->setTime(9, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertSee($this->patient->full_name)
        ->assertSee('Todays appointments (1)');
});

it('uses the session branch when the user has no default branch_id', function (): void {
    $this->user->update(['branch_id' => null]);
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->setTime(14, 0),
        'end_at' => now()->setTime(14, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertSee('Todays appointments (1)');
});

it('shows header actions after picking a patient without a full page reload', function (): void {
    $this->actingAs($this->user);

    $page = Livewire::test(ClinicalWorkspace::class);

    expect($page->instance()->getCachedHeaderActions())->toBeEmpty();

    $page->call('selectPatient', $this->patient->id)
        ->assertSet('mode', 'patient');

    $actions = $page->instance()->getCachedHeaderActions();
    $actionKeys = collect($actions)
        ->map(fn ($action) => $action instanceof Action
            ? $action->getName()
            : 'group:'.$action->getLabel())
        ->all();

    expect($actions)->not->toBeEmpty()
        ->and($actions[0]->getName())->toBe('view_timeline')
        ->and($actions[1]->getName())->toBe('view_profile')
        ->and($actionKeys)->toBe(array_values(array_unique($actionKeys)));
});

it('does not duplicate header actions when the workspace mounts with a patient', function (): void {
    $this->actingAs($this->user);

    $actions = Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('mode', 'patient')
        ->instance()
        ->getCachedHeaderActions();

    $actionKeys = collect($actions)
        ->map(fn ($action) => $action instanceof Action
            ? $action->getName()
            : 'group:'.$action->getLabel())
        ->all();

    expect($actionKeys)->toContain('view_timeline', 'view_profile')
        ->and($actionKeys)->toBe(array_values(array_unique($actionKeys)));
});
