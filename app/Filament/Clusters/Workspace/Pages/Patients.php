<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Filament\Widgets\MyTasksWidget;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Filament\Widgets\RecentPatientsWidget;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;

class Patients extends Page implements HasActions, HasInfolists, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithInfolists, InteractsWithSchemas, InteractsWithTable;
    use HasPageShield;

    protected static ?string $slug = '';

    protected static ?string $navigationLabel = 'Patient Workspace';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::HeartPulse;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'clinical::clinical.workspace.patient-list';

    public string $viewMode = 'card';

    public string $search = '';

    public string $encounterStatusFilter = 'all';

    public string $dateRangeFilter = 'all';

    public string $encounterTypeFilter = 'all';

    public int $perPage = 12;

    public function mount(): void
    {
        $this->applyFiltersFromUrl();
    }

    public function applyFiltersFromUrl(): void
    {
        $this->encounterStatusFilter = request()->input('status', 'all');
        $this->dateRangeFilter = request()->input('date', 'all');
        $this->encounterTypeFilter = request()->input('type', 'all');
    }

    public function resetFilters()
    {
        $this->search = '';
        $this->encounterStatusFilter = 'all';
        $this->dateRangeFilter = 'all';
        $this->encounterTypeFilter = 'all';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedEncounterStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateRangeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedEncounterTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function toggleViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function getPatients()
    {
        return Patient::query()
            ->with(['latestEncounter', 'activeEncounter', 'latestVitals', 'allergies'])
            ->when($this->search, fn ($query) => $query->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                    ->orWhere('mrn', 'like', "%{$this->search}%")
                    ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->when($this->encounterStatusFilter !== 'all', function ($query) {
                if ($this->encounterStatusFilter === 'active') {
                    $query->whereHas('encounters', fn ($q) => $q->whereIn('status', [
                        EncounterStatus::ARRIVED->value,
                        EncounterStatus::TRIAGED->value,
                        EncounterStatus::IN_PROGRESS->value,
                        EncounterStatus::ON_LEAVE->value,
                    ]));
                } elseif ($this->encounterStatusFilter === 'inactive') {
                    $query->whereDoesntHave('encounters', fn ($q) => $q->whereIn('status', [
                        EncounterStatus::ARRIVED->value,
                        EncounterStatus::TRIAGED->value,
                        EncounterStatus::IN_PROGRESS->value,
                        EncounterStatus::ON_LEAVE->value,
                    ]));
                }
            })
            ->when($this->encounterTypeFilter !== 'all', fn ($query) => $query->whereHas('encounters', fn ($q) => $q->where('type', $this->encounterTypeFilter)))
            ->when($this->dateRangeFilter !== 'all', fn ($query) => $this->applyDateRange($query))
            ->orderByDesc('updated_at')
            ->paginate($this->perPage);
    }

    protected function applyDateRange($query): void
    {
        $date = match ($this->dateRangeFilter) {
            'today' => now()->startOfDay(),
            'this_week' => now()->startOfWeek(),
            'this_month' => now()->startOfMonth(),
            default => null,
        };

        if ($date) {
            $query->whereHas('encounters', fn ($q) => $q->where('created_at', '>=', $date));
        }
    }

    public function patientInfoList(Patient $patient): Schema
    {
        return $this->makeSchema()
            ->record($patient)
            ->components([
                Section::make()
                    ->headerActions([
                        PatientResource::profileAction(),
                        PatientResource::timelineAction(),
                    ])
                    ->schema([
                        // Larger, more prominent photo
                        ImageEntry::make('photo')
                            ->imageSize(120)
                            ->hiddenLabel()
                            ->alignCenter()
                            ->circular()
                            ->extraAttributes(['class' => 'mt-6']),

                        TextEntry::make('full_name')
                            ->label('Name')
                            ->weight('bold')
                            ->size('lg')
                            ->alignCenter(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('mrn')->label('MRN'),
                                TextEntry::make('age')->label('Age'),
                                TextEntry::make('gender')
                                    ->label('Gender')
                                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
                                TextEntry::make('activeEncounter.status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),
                                TextEntry::make('activeEncounter.type')
                                    ->label('Type')
                                    ->badge()
                                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),
                            ])
                            ->columns(2),

                    ])
                    ->extraAttributes(['class' => 'px-6 pb-8 text-center']),
            ]);
    }

    public function table(Table $table): Table
    {
        $patients = $this->getPatients();
        $isCardView = $this->viewMode === 'card';

        return $table
            ->query(fn () => $patients?->count() > 0 ? $patients?->toQuery() : null)
            ->defaultSort('updated_at', 'desc')
            ->paginated(false)
            ->columns($isCardView ? $this->getCardColumns() : $this->getTableColumns())
            ->contentGrid($isCardView ? ['md' => 3, 'xl' => 4] : null)
            ->recordActions([
                PatientResource::profileAction(),
                PatientResource::timelineAction(),
            ], position: $isCardView ? RecordActionsPosition::BeforeColumns: null);
    }

    protected function getCardColumns(): array
    {
        return [
            Stack::make([
                ImageColumn::make('photo')
                    ->circular()
                    ->imageSize(80)
                    ->alignCenter()
                    ->extraAttributes(['class' => 'mt-2'])
                    ->state(fn (Patient $record) => $record->photo_url),
                TextColumn::make('full_name')
                    ->size('lg')
                    ->weight('bold')
                    ->alignCenter()
                    ->searchable(['first_name', 'last_name', 'mrn', 'phone']),
                TextColumn::make('mrn')
                    ->label('MRN')
                    ->alignCenter()
                    ->color('gray'),
                TextColumn::make('age_gender')
                    ->label('Age / Gender')
                    ->alignCenter()
                    ->formatStateUsing(fn (Patient $record) => $record->age . ' / ' . ($record->gender?->getLabel() ?? 'N/A')),
                TextColumn::make('activeEncounter.status')
                    ->label('Status')
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state?->getColor() ?? 'gray'),
                TextColumn::make('allergies_count')
                    ->label('Allergies')
                    ->alignCenter()
                    ->icon(fn (Patient $record) => $record->allergies?->isNotEmpty() ? 'heroicon-m-exclamation-triangle' : '')
                    ->iconColor(fn (Patient $record) => $record->allergies?->isNotEmpty() ? 'danger' : '')
                    ->formatStateUsing(fn (Patient $record) => $record->allergies?->count() ?? 0),
            ]),
        ];
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('full_name')
                ->weight('bold'),
            TextColumn::make('mrn')
                ->label('MRN')
                ->searchable()
                ->sortable(),
            TextColumn::make('age')
                ->label('Age'),
            TextColumn::make('gender')
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? 'N/A'),
            TextColumn::make('activeEncounter.status')
                ->label('Status')
                ->badge()
                ->color(fn ($state) => $state?->getColor() ?? 'gray'),
            TextColumn::make('activeEncounter.type')
                ->label('Type')
                ->badge()
                ->color(fn ($state) => $state?->getColor() ?? 'gray'),
        ];
    }

    public function getEncounterStatusOptions(): array
    {
        return [
            'all' => 'All',
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
    }

    public function getDateRangeOptions(): array
    {
        return [
            'all' => 'All Time',
            'today' => 'Today',
            'this_week' => 'This Week',
            'this_month' => 'This Month',
        ];
    }

    public function getEncounterTypeOptions(): array
    {
        return [
            'all' => 'All Types',
            EncounterType::EMERGENCY->value => 'Emergency',
            EncounterType::INPATIENT->value => 'Inpatient',
            EncounterType::OUTPATIENT->value => 'Outpatient',
            EncounterType::VIRTUAL->value => 'Virtual',
        ];
    }

    public function getPerPageOptions(): array
    {
        return [
            12 => '12',
            24 => '24',
            48 => '48',
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CriticalPatientsWidget::class,
            MyTasksWidget::class,
            PatientTimelineWidget::class,
            RecentPatientsWidget::class,
        ];
    }
}
