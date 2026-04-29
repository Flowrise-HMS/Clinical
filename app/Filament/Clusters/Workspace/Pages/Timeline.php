<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
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

    public int $timelineLimit = 15;

    public int $timelineIncrement = 10;

    public bool $hasMoreEvents = false;

    public bool $isLoadingMore = false;

    protected string $view = 'clinical::clinical.workspace.pages.timeline';

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->activeFilter = $this->normalizeFilter(request()->query('filter', 'all'));
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
        if (! $this->workspaceService || ! $this->currentPatient) {
            $this->timelineEvents = collect();
            $this->hasMoreEvents = false;

            return;
        }

        $type = $this->activeFilter === 'all' ? null : $this->activeFilter;
        $this->timelineEvents = $this->workspaceService->getTimelineEvents($this->timelineLimit, $type);

        $counts = $this->workspaceService->getTimelineEventCounts();
        $targetCount = $type ? ($counts[$type] ?? 0) : ($counts['all'] ?? 0);
        $this->hasMoreEvents = $this->timelineEvents->count() < $targetCount;
    }

    public function loadMoreEvents(): void
    {
        if ($this->isLoadingMore || ! $this->hasMoreEvents) {
            return;
        }

        $this->isLoadingMore = true;
        $this->timelineLimit += $this->timelineIncrement;
        $this->loadTimelineData();
        $this->isLoadingMore = false;
    }

    public function getEventCounts(): array
    {
        if (! $this->currentPatient) {
            return [
                'all' => 0, 'encounter' => 0, 'vitals' => 0, 'note' => 0, 'order' => 0
            ];
        }

        $service = app(ClinicalWorkspaceService::class)
            ->setPatient($this->currentPatient);

        if ($this->currentEncounter) {
            $service->setEncounter($this->currentEncounter);
        }

        return $service->getTimelineEventCounts();
    }

    public function getTimelineEvents(): Collection
    {
        return $this->timelineEvents ?? collect();
    }

    protected function normalizeFilter(?string $filter): string
    {
        $allowed = ['all', 'encounter', 'vitals', 'note', 'order'];

        return in_array($filter, $allowed, true) ? $filter : 'all';
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
