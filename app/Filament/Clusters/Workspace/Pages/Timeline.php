<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;

class Timeline extends Page
{
    use HasPatientContext;

    protected static ?string $title = 'Timeline';

    protected static ?string $navigationLabel = 'Timeline';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Clock;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'patient/{patient}/timeline';

    public ?string $activeFilter = 'all';

    public Collection|array|null $timelineEvents;

    protected string $view = 'clinical::clinical.workspace.pages.timeline';

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->activeFilter = request()->query('filter', 'all');
        $this->bootHasPatientContext();
        $this->loadTimelineData();
    }


    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()->forPatient($this->currentPatient);

        return $actions->timelineQuickActions();
    }

    protected function loadTimelineData(): void
    {
        if ($this->workspaceService) {
            $type = $this->activeFilter === 'all' ? null : $this->activeFilter;
            $this->timelineEvents = $this->workspaceService->getTimelineEvents(15, $type);
        }
    }

    public function getEventCounts(): array
    {
        return $this->workspaceService?->getTimelineEventCounts() ?? [
            'all' => 0, 'encounter' => 0, 'vitals' => 0, 'note' => 0, 'order' => 0
        ];
    }

    public function getTimelineEvents(): Collection
    {
        return $this->timelineEvents ?? collect();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getTitle(): string
    {
        return $this->currentPatient
            ? 'Timeline - '.$this->currentPatient->full_name."({$this->currentPatient->mrn})"
            : 'Timeline';
    }
}
