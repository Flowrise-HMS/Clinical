<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Support\OptionalClass;

class WorkspaceTodayAppointmentsWidget extends Widget
{
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
}
