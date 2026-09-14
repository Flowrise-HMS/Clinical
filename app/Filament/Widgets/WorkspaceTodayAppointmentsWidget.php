<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Support\OptionalClass;

class WorkspaceTodayAppointmentsWidget extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'clinical::filament.widgets.workspace-today-appointments-widget';

    protected static bool $isDiscovered = false;

    protected int $sorting = 3;

    public Collection $appointments;

    public function mount(): void
    {
        $this->loadAppointments();
    }

    #[On('refresh-workspace-appointments')]
    public function refreshAppointments(): void
    {
        $this->loadAppointments();
    }

    protected function loadAppointments(): void
    {
        $appointmentClass = OptionalClass::resolve('Modules\\Appointment\\Models\\Appointment', 'Appointment');

        if ($appointmentClass === null || ! Auth::check()) {
            $this->appointments = collect();

            return;
        }

        $branchId = app(BranchService::class)->getDefaultBranchId();

        $this->appointments = $appointmentClass::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->whereDate('start_at', now()->toDateString())
            ->whereNotIn('status', ['cancelled', 'noshow'])
            ->with(['patient', 'location'])
            ->orderBy('start_at')
            ->limit(25)
            ->get();
    }

    public function appointmentViewUrl(object $appointment): ?string
    {
        if (! Auth::check() || ! Auth::user()->can('view', $appointment)) {
            return null;
        }

        return OptionalClass::when(
            'Modules\\Appointment\\Filament\\Clusters\\Appointment\\Resources\\Appointments\\AppointmentResource',
            fn (string $resource) => $resource::getUrl('view', ['record' => $appointment]),
            'Appointment',
        );
    }

    /**
     * Whether the current user may check this appointment in from the list.
     */
    public function canCheckIn(object $appointment): bool
    {
        if (! Auth::check() || ! Auth::user()->can('update', $appointment)) {
            return false;
        }

        $status = $appointment->status;

        return is_object($status)
            && method_exists($status, 'allowsCheckIn')
            && $status->allowsCheckIn();
    }

    /**
     * Check the patient in, then reload the workspace with that patient already
     * selected so the clinician does not have to search for them again.
     */
    public function checkInAction(): Action
    {
        return Action::make('checkIn')
            ->label(__('Check in'))
            ->icon('heroicon-m-arrow-right-circle')
            ->color('success')
            ->size('xs')
            ->requiresConfirmation()
            ->modalHeading(__('Check in patient'))
            ->modalDescription(function (array $arguments): string {
                $appointment = $this->findAppointment($arguments['appointment'] ?? null);
                $name = $appointment?->patient?->full_name ?? __('this patient');

                return __('Mark :name as arrived and open them in the workspace?', ['name' => $name]);
            })
            ->modalSubmitActionLabel(__('Check in'))
            ->action(function (array $arguments): void {
                $appointment = $this->findAppointment($arguments['appointment'] ?? null);

                if ($appointment === null || ! $this->canCheckIn($appointment)) {
                    Notification::make()
                        ->title(__('This appointment can no longer be checked in.'))
                        ->warning()
                        ->send();
                    $this->loadAppointments();

                    return;
                }

                $schedulingServiceClass = OptionalClass::resolve(
                    'Modules\\Appointment\\Classes\\Services\\AppointmentSchedulingService',
                    'Appointment',
                );

                if ($schedulingServiceClass === null) {
                    Notification::make()
                        ->title(__('Appointment module is not available'))
                        ->warning()
                        ->send();

                    return;
                }

                $appointment = app($schedulingServiceClass)->checkIn($appointment);

                Notification::make()
                    ->title(__('Patient checked in'))
                    ->success()
                    ->send();

                if (! $appointment->patient_id) {
                    $this->loadAppointments();

                    return;
                }

                $this->redirect(ClinicalWorkspace::getUrl([
                    'patientId' => (string) $appointment->patient_id,
                ]));
            });
    }

    protected function findAppointment(?string $id): ?object
    {
        if ($id === null || $id === '') {
            return null;
        }

        $appointmentClass = OptionalClass::resolve('Modules\\Appointment\\Models\\Appointment', 'Appointment');

        if ($appointmentClass === null) {
            return null;
        }

        return $appointmentClass::query()->with('patient')->find($id);
    }
}
