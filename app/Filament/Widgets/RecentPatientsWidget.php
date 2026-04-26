<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;

class RecentPatientsWidget extends Widget
{
    protected string $view = 'clinical::widgets.recent-patients-widget';

    protected int $sorting = 4;

    protected static bool $isDiscovered = false;

    public Collection $patients;

    public function mount(): void
    {
        $this->loadPatients();
    }

    protected function loadPatients(): void
    {
        $workspaceService = app(ClinicalWorkspaceService::class);
        $this->patients = $workspaceService->getRecentPatients(5);
    }
}
