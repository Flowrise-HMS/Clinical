<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Modules\Appointment\Filament\Clusters\Appointment\Resources\Appointments\AppointmentResource;
use Modules\Appointment\Models\Appointment;

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
        if (! class_exists(Appointment::class) || ! Auth::check()) {
            $this->appointments = collect();

            return;
        }

        $user = Auth::user();
        $branchId = $user->branch_id ?? null;

        if (! $branchId) {
            $this->appointments = collect();

            return;
        }

        $start = now()->startOfDay();
        $end = now()->endOfDay();

        $this->appointments = Appointment::query()
            ->where('branch_id', $branchId)
            ->where('start_at', '<=', $end)
            ->where('end_at', '>=', $start)
            ->with(['patient', 'location'])
            ->orderBy('start_at')
            ->limit(25)
            ->get();
    }

    public function appointmentViewUrl(Appointment $appointment): ?string
    {
        if (! Auth::check() || ! Auth::user()->can('view', $appointment)) {
            return null;
        }

        return AppointmentResource::getUrl('view', ['record' => $appointment]);
    }
}
