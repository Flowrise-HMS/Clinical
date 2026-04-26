<?php

namespace Modules\Clinical\Filament\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;

class CriticalPatientsWidget extends Widget
{
    // use HasWidgetShield;

    protected string $view = 'clinical::widgets.critical-patients-widget';

    protected int $sorting = 1;

    protected static bool $isDiscovered = false;

    public Collection $criticalPatients;

    public function mount(): void
    {
        $this->loadCriticalPatients();
    }

    protected function loadCriticalPatients(): void
    {
        $workspaceService = app(ClinicalWorkspaceService::class);
        $this->criticalPatients = $workspaceService->getCriticalPatients();
    }
}
