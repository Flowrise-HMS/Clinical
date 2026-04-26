<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Models\Encounter;
use Modules\Patient\Models\Patient;

class PatientTimelineWidget extends Widget
{
    protected string $view = 'clinical::widgets.patient-timeline-widget';

    protected static bool $isDiscovered = false;

    protected int $sorting = 3;

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    public Collection $events;

    public bool $isLoading = false;

    public function mount(): void
    {
        $this->loadEvents();
    }

    public function updatedPatientId(): void
    {
        $this->loadEvents();
    }

    public function updatedEncounterId(): void
    {
        $this->loadEvents();
    }

    protected function loadEvents(): void
    {
        if (! $this->patientId) {
            $this->events = collect();

            return;
        }

        $workspaceService = app(ClinicalWorkspaceService::class);
        $patient = Patient::find($this->patientId);

        if (! $patient) {
            $this->events = collect();

            return;
        }

        $workspaceService->setPatient($patient);

        if ($this->encounterId) {
            $encounter = Encounter::find($this->encounterId);
            $workspaceService->setEncounter($encounter);
        }

        $this->events = $workspaceService->getTimelineEvents();
    }

    protected function getViewData(): array
    {
        return [
            'events' => $this->events ?? collect(),
        ];
    }
}
