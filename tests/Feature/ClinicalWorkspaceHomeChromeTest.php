<?php

use App\Models\User;
use Filament\Actions\Action;
use Livewire\Livewire;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Appointment\Models\Appointment;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Filament\Widgets\MyTasksWidget;
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
        ->assertSee("Today's appointments (1)");
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
        ->assertSee("Today's appointments (1)");
});

it('shows header actions after picking a patient without a full page reload', function (): void {
    $this->actingAs($this->user);

    $page = Livewire::test(ClinicalWorkspace::class);

    $homeActionKeys = collect($page->instance()->getCachedHeaderActions())
        ->map(fn ($action) => $action instanceof Action ? $action->getName() : 'group:'.$action->getLabel())
        ->all();

    expect($homeActionKeys)->not->toContain('view_timeline', 'view_profile');

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

it('checks a patient in from the widget and hands them to the workspace', function (): void {
    Permission::findOrCreate('Update Appointment', 'web');
    $this->user->givePermissionTo('Update Appointment');
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->setTime(10, 0),
        'end_at' => now()->setTime(10, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertActionVisible('checkIn', ['appointment' => $appointment->id])
        ->callAction('checkIn', arguments: ['appointment' => $appointment->id])
        ->assertNotified('Patient checked in')
        ->assertRedirect(ClinicalWorkspace::getUrl(['patientId' => $this->patient->id]));

    $appointment->refresh();

    expect($appointment->status)->toBe(AppointmentStatus::ARRIVED)
        ->and($appointment->checked_in_at)->not->toBeNull();

    Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('mode', 'patient')
        ->assertSet('currentPatient.id', $this->patient->id);
});

it('hides the check-in button when the user cannot update appointments', function (): void {
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->setTime(11, 0),
        'end_at' => now()->setTime(11, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertSee($this->patient->full_name)
        ->assertDontSee('Check in');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::BOOKED);
});

it('hides the check-in button once the patient has already arrived', function (): void {
    Permission::findOrCreate('Update Appointment', 'web');
    $this->user->givePermissionTo('Update Appointment');
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::ARRIVED,
        'start_at' => now()->setTime(12, 0),
        'end_at' => now()->setTime(12, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertSee('Arrived')
        ->assertDontSee('Check in');
});

it('offers check-in to a user with no assigned branch working in the session branch', function (): void {
    Permission::findOrCreate('Update Appointment', 'web');
    $this->user->givePermissionTo('Update Appointment');
    $this->user->update(['branch_id' => null]);
    $this->actingAs($this->user);
    session(['current_branch_id' => $this->branch->id]);

    $appointment = Appointment::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => AppointmentStatus::BOOKED,
        'start_at' => now()->setTime(13, 0),
        'end_at' => now()->setTime(13, 30),
    ]);

    Livewire::test(WorkspaceTodayAppointmentsWidget::class)
        ->assertOk()
        ->assertSee('Check in')
        ->callAction('checkIn', arguments: ['appointment' => $appointment->id])
        ->assertRedirect(ClinicalWorkspace::getUrl(['patientId' => $this->patient->id]));

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::ARRIVED);
});

it('renders the patient search above the home dashboard widgets', function (): void {
    $this->actingAs($this->user);

    $page = Livewire::test(ClinicalWorkspace::class)->assertSet('mode', 'home');

    // The dashboard lives in the footer so the search box stays above the fold.
    expect($page->instance()->getVisibleHeaderWidgets())->toBe([]);

    $footerWidgets = collect($page->instance()->getVisibleFooterWidgets())
        ->map(fn ($widget) => is_string($widget) ? $widget : $widget->widget)
        ->all();

    // Widgets are lazy-loaded, so only their placeholders are in the first render;
    // Filament always places footer widgets after the page content (the search).
    expect($footerWidgets)->toContain(CriticalPatientsWidget::class, MyTasksWidget::class);

    $page->assertSee('Search patients by name, MRN, or phone...');
});

it('does not render the home dashboard widgets once a patient is selected', function (): void {
    $this->actingAs($this->user);

    $footerWidgets = collect(
        Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
            ->assertSet('mode', 'patient')
            ->instance()
            ->getVisibleFooterWidgets()
    )->map(fn ($widget) => is_string($widget) ? $widget : $widget->widget)->all();

    expect($footerWidgets)->not->toContain(CriticalPatientsWidget::class, MyTasksWidget::class);
});

it('offers the quick add-appointment action on the workspace home', function (): void {
    Permission::findOrCreate('Create Appointment', 'web');
    $this->user->givePermissionTo('Create Appointment');
    $this->actingAs($this->user);

    $actions = Livewire::test(ClinicalWorkspace::class)
        ->assertSet('mode', 'home')
        ->instance()
        ->getCachedHeaderActions();

    $quickCreate = collect($actions)->first(fn ($action) => $action instanceof Action && $action->getName() === 'appointment.quick_create');

    expect($quickCreate)->not->toBeNull()
        ->and($quickCreate->isVisible())->toBeTrue();
});

it('hides the quick add-appointment action once a patient is selected', function (): void {
    Permission::findOrCreate('Create Appointment', 'web');
    $this->user->givePermissionTo('Create Appointment');
    $this->actingAs($this->user);

    $actions = collect(Livewire::test(ClinicalWorkspace::class, ['patientId' => $this->patient->id])
        ->assertSet('mode', 'patient')
        ->instance()
        ->getCachedHeaderActions())
        ->filter(fn ($action) => $action instanceof Action)
        ->keyBy(fn (Action $action) => $action->getName());

    expect($actions->get('appointment.schedule')?->isVisible())->toBeTrue()
        ->and($actions->get('appointment.quick_create')?->isVisible())->toBeFalse();
});
