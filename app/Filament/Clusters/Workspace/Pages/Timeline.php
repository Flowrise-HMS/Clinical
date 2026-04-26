<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Navigation\NavigationItem;
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

    public Collection|array|null $timelineEvents;

    protected string $view = 'clinical::clinical.workspace.pages.timeline';

    public function boot(): void
    {
        $this->patientId = request()->route('patient');
        $this->bootHasPatientContext();
        $this->loadTimelineData();
    }

    public function getSubNavigation(): array
    {
        return [
            NavigationItem::make('Notes')
                ->icon(Notes::getActiveNavigationIcon())
                ->url(fn () => Notes::getUrl(['patient' => $this->currentPatient]), shouldOpenInNewTab: true),
            NavigationItem::make('Orders')
                ->icon(Orders::getActiveNavigationIcon())
                ->url(fn () => Orders::getUrl(['patient' => $this->currentPatient]), shouldOpenInNewTab: true),
            NavigationItem::make('Vitals')
                ->icon(Vitals::getActiveNavigationIcon())
                ->url(fn () => Vitals::getUrl(['patient' => $this->currentPatient]), shouldOpenInNewTab: true),
        ];
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
            $this->timelineEvents = $this->workspaceService->getTimelineEvents();
        }
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
