<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Models\Encounter;
use Modules\Patient\Models\Patient;

trait HasPatientContext
{
    #[Url]
    public ?string $patientId = null;

    public ?Patient $currentPatient = null;

    public ?Encounter $currentEncounter = null;

    public ?object $latestVitals = null;

    protected ?ClinicalWorkspaceService $workspaceService = null;

    public function bootHasPatientContext(): void
    {
        $this->workspaceService = app(ClinicalWorkspaceService::class);
        $this->loadPatientContext();
    }

    public function mountHasPatientContext(): void
    {
        $this->workspaceService = app(ClinicalWorkspaceService::class);
    }

    protected function loadPatientContext(): void
    {
        if ($this->patientId) {
            $this->currentPatient = Patient::with([
                'allergies',
                'latestEncounter',
                'latestVitals',
            ])->find($this->patientId);

            if ($this->currentPatient) {
                $this->workspaceService->setPatient($this->currentPatient);
                $this->currentEncounter = $this->currentPatient->latestEncounter;
                $this->latestVitals = $this->workspaceService->getLatestVitals();
            }
        }
    }
}
