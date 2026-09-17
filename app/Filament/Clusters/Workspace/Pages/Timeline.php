<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Classes\Services\CanvasLayoutService;
use Modules\Clinical\Classes\Services\ClinicalCanvasTreeBuilder;
use Modules\Clinical\Classes\Services\ClinicalWorkspaceService;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Core\Classes\Support\PageWidgetsRegistry;

class Timeline extends Page
{
    use HasPageShield;
    use HasPatientContext;

    protected static ?string $title = 'Timeline';

    protected static ?string $navigationLabel = 'Timeline';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::Clock;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'patient/{patient}/timeline';

    #[Url(as: 'filter', except: 'all')]
    public string $activeFilter = 'all';

    #[Url(as: 'view', except: 'list')]
    public string $displayMode = 'list';

    /**
     * Root node of the patient hierarchy rendered by the canvas view.
     *
     * @var array<string, mixed>|null
     */
    public ?array $canvasTree = null;

    /**
     * @var array<string, mixed>
     */
    public array $canvasMeta = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $savedLayout = null;

    public Collection|array|null $timelineEvents;

    public int $timelineLimit = 15;

    public int $timelineIncrement = 10;

    public bool $hasMoreEvents = false;

    public bool $isLoadingMore = false;

    protected string $view = 'clinical::clinical.workspace.pages.timeline';

    public function boot(): void
    {
        $this->patientId = request()->route('patient') ?? $this->patientId;
        $this->activeFilter = $this->normalizeFilter($this->activeFilter);
        $this->displayMode = $this->normalizeView($this->displayMode);
        $this->bootHasPatientContext();
        $this->loadTimelineData();
    }

    public function setView(string $view): void
    {
        $normalized = $this->normalizeView($view);

        if ($this->displayMode === $normalized) {
            return;
        }

        $this->displayMode = $normalized;
        $this->loadTimelineData();
    }

    /**
     * @param  array<string, mixed>  $layout
     */
    public function saveLayout(array $layout): void
    {
        $user = Auth::user();

        if (! $user || ! $this->currentPatient) {
            return;
        }

        try {
            $saved = app(CanvasLayoutService::class)->save(
                $user,
                CanvasLayout::KEY_TIMELINE,
                $this->currentPatient->id,
                null,
                $layout,
            );

            $this->savedLayout = $saved->layout;
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Canvas layout not saved')
                ->body($e->validator->errors()->first())
                ->danger()
                ->send();
        }
    }

    public function resetLayout(): void
    {
        $user = Auth::user();

        if (! $user || ! $this->currentPatient) {
            return;
        }

        app(CanvasLayoutService::class)->reset($user, CanvasLayout::KEY_TIMELINE, $this->currentPatient->id);
        $this->savedLayout = null;
    }

    public function mount(): void
    {
        $this->mountHasPatientContext();
    }

    public function setFilter(string $filter): void
    {
        $normalized = $this->normalizeFilter($filter);

        if ($this->activeFilter === $normalized) {
            return;
        }

        $this->activeFilter = $normalized;
        $this->timelineLimit = 15;
        $this->loadTimelineData();
    }

    protected function getHeaderActions(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        $actions = PatientActions::make()->forPatient($this->currentPatient);

        return [
            ...$actions->timelineQuickActions(),
            ...app(PageHeaderActionsRegistry::class)->for(static::class, $this),
        ];
    }

    protected function loadTimelineData(): void
    {
        if (! $this->workspaceService || ! $this->currentPatient) {
            $this->timelineEvents = collect();
            $this->hasMoreEvents = false;

            return;
        }

        // Patient-wide timeline — do not scope to the active/latest encounter.
        $this->workspaceService
            ->setPatient($this->currentPatient)
            ->clearEncounter();

        $type = $this->activeFilter === 'all' ? null : $this->activeFilter;
        $counts = $this->workspaceService->getTimelineEventCounts();
        $targetCount = $type ? ($counts[$type] ?? 0) : ($counts['all'] ?? 0);

        if ($this->displayMode === 'canvas') {
            $this->timelineEvents = collect();
            $this->hasMoreEvents = false;

            $user = Auth::user();
            ['root' => $this->canvasTree, 'meta' => $this->canvasMeta] = app(ClinicalCanvasTreeBuilder::class)
                ->buildPatientTree($this->currentPatient, $user);

            $this->savedLayout = $user
                ? app(CanvasLayoutService::class)->get($user, CanvasLayout::KEY_TIMELINE, $this->currentPatient->id)
                : null;

            return;
        }

        $this->timelineEvents = $this->workspaceService->getTimelineEvents($this->timelineLimit, $type);
        $this->hasMoreEvents = $this->timelineEvents->count() < $targetCount;
        $this->canvasTree = null;
        $this->canvasMeta = [];
    }

    protected function normalizeView(?string $view): string
    {
        return $view === 'canvas' ? 'canvas' : 'list';
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
                'all' => 0, 'encounter' => 0, 'vitals' => 0, 'note' => 0, 'order' => 0, 'appointment' => 0,
            ];
        }

        return app(ClinicalWorkspaceService::class)
            ->setPatient($this->currentPatient)
            ->clearEncounter()
            ->getTimelineEventCounts();
    }

    public function getTimelineEvents(): Collection
    {
        return $this->timelineEvents ?? collect();
    }

    protected function normalizeFilter(?string $filter): string
    {
        $allowed = ['all', 'encounter', 'vitals', 'note', 'order', 'appointment'];

        return in_array($filter, $allowed, true) ? $filter : 'all';
    }

    protected function getFooterWidgets(): array
    {
        if (! $this->currentPatient) {
            return [];
        }

        return [
            ...app(PageWidgetsRegistry::class)->for(static::class, 'footer', $this),
        ];
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
