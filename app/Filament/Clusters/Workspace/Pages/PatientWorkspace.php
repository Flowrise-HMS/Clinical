<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Modules\Clinical\Classes\Actions\PatientActions;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Filament\Clusters\Workspace\WorkspaceCluster;
use Modules\Clinical\Filament\Widgets\CriticalPatientsWidget;
use Modules\Clinical\Filament\Widgets\MyTasksWidget;
use Modules\Clinical\Filament\Widgets\PatientTimelineWidget;
use Modules\Clinical\Filament\Widgets\PendingFulfillmentsWidget;
use Modules\Clinical\Filament\Widgets\RecentPatientsWidget;
use Modules\Clinical\Filament\Widgets\WorkspaceTodayAppointmentsWidget;
use Modules\Core\Classes\Support\PageHeaderActionsRegistry;
use Modules\Patient\Classes\Services\PatientSearchService;
use Modules\Patient\Models\Patient;

class PatientWorkspace extends Page implements HasActions, HasSchemas, HasTable
{
    use HasPageShield;
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable;

    protected static ?string $slug = 'legacy-patient-list';

    protected static ?string $navigationLabel = 'Patient Workspace (Legacy)';

    protected static ?string $cluster = WorkspaceCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::HeartPulse;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'clinical::clinical.workspace.patient-list';

    public string $viewMode;

    public string $viewModeSessionKey;

    public function mount(): void
    {
        $this->viewModeSessionKey = Auth::id().'_patients_view_mode';
        $this->viewMode = Session::get($this->viewModeSessionKey, 'card');
    }

    public function toggleViewMode(string $mode): void
    {
        if ($mode != $this->viewMode) {
            $this->viewMode = $mode;
            Session::put($this->viewModeSessionKey, $mode);
            $this->resetTable();
            Notification::make()->title("View mode changed to <b>{$mode}</b>")->success()->send();
        }
    }

    public function getPatients()
    {
        $relations = ['latestEncounter', 'activeEncounter', 'latestVitals', 'allergies'];
        if ($this->hasAppointmentModule()) {
            $relations[] = 'appointments';
        }

        return Patient::query()
            ->with($relations)
            ->orderByDesc('updated_at');
    }

    public function table(Table $table): Table
    {
        $actions = app(PatientActions::class);

        return $table
            ->query(Patient::query()->with(array_filter([
                'latestEncounter',
                'activeEncounter',
                'latestVitals',
                'allergies',
                $this->hasAppointmentModule() ? 'appointments' : null,
            ])))
            ->defaultSort('updated_at', 'desc')
            ->paginationPageOptions([12, 24, 48])
            ->searchable(app(PatientSearchService::class)->getSearchableFields())
            ->columns($this->viewMode === 'card' ? $this->getCardColumns() : $this->getTableColumns())
            ->contentGrid($this->viewMode === 'card' ? ['md' => 3, 'xl' => 4] : null)
            ->heading('Patients')
            ->filters([
                Filter::make('filters')
                    ->schema([
                        Select::make('status')
                            ->options(fn () => $this->getEncounterStatusOptions())
                            ->placeholder('All Status'),
                        Select::make('type')
                            ->options(fn () => $this->getEncounterTypeOptions())
                            ->placeholder('All Types'),
                        Select::make('date_range')
                            ->options(fn () => $this->getDateRangeOptions())
                            ->placeholder('All Time'),
                        Select::make('appointment_status')
                            ->options(fn () => $this->getAppointmentStatusOptions())
                            ->placeholder('All Appointments')
                            ->visible(fn () => $this->hasAppointmentModule()),
                    ])
                    ->query(function ($query, array $data) {
                        $activeStatuses = array_filter($this->getEncounterTypeOptions(), fn ($value) => $value != 'all');

                        $query->when(($data['status'] ?? 'all') !== 'all', function ($q) use ($data, $activeStatuses) {
                            if ($data['status'] === 'active') {
                                $q->whereHas('encounters', fn ($q) => $q->whereIn('status', $activeStatuses));
                            } else {
                                $q->whereDoesntHave('encounters', fn ($q) => $q->whereIn('status', $activeStatuses));
                            }
                        })
                            ->when(($data['type'] ?? 'all') !== 'all', function ($q) use ($data) {
                                $q->whereHas('encounters', fn ($q) => $q->where('type', $data['type']));
                            })
                            ->when(($data['date_range'] ?? 'all') !== 'all', function ($q) use ($data) {
                                $date = match ($data['date_range']) {
                                    'today' => now()->startOfDay(),
                                    'this_week' => now()->startOfWeek(),
                                    'this_month' => now()->startOfMonth(),
                                    default => null,
                                };
                                if ($date) {
                                    $q->whereHas('encounters', fn ($q) => $q->where('created_at', '>=', $date));
                                }
                            })
                            ->when(($data['appointment_status'] ?? 'all') !== 'all', function ($q) use ($data) {
                                if ($this->hasAppointmentModule()) {
                                    $q->whereHas('appointments', fn ($aq) => $aq->where('status', $data['appointment_status']));
                                }
                            });
                    }),
            ])
            ->recordActions([
                $actions->profileAction(),
                $actions->timelineAction(),
            ], position: $this->viewMode === 'card' ? RecordActionsPosition::BeforeColumns : null);
    }

    protected function getCardColumns(): array
    {
        return [
            Stack::make([
                ImageColumn::make('photo')
                    ->circular()
                    ->imageSize(80)
                    ->alignCenter()
                    ->extraAttributes(['class' => 'mt-2']),
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
                    ->formatStateUsing(fn (Patient $record) => $record->age.' / '.($record->gender?->getLabel() ?? 'N/A')),
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
            TextColumn::make('#')->rowIndex(),
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

    public function getAppointmentStatusOptions(): array
    {
        if (! $this->hasAppointmentModule()) {
            return ['all' => 'All Appointments'];
        }

        return [
            'all' => 'All Appointments',
            'booked' => 'Booked',
            'arrived' => 'Arrived',
            'cancelled' => 'Cancelled',
            'noshow' => 'No-show',
        ];
    }

    protected function hasAppointmentModule(): bool
    {
        return class_exists('Modules\\Appointment\\Models\\Appointment');
    }

    protected function getHeaderActions(): array
    {
        return app(PageHeaderActionsRegistry::class)->for(static::class, $this);
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [
            CriticalPatientsWidget::class,
            MyTasksWidget::class,
        ];

        if ($this->hasAppointmentModule()) {
            $widgets[] = WorkspaceTodayAppointmentsWidget::class;
        }

        $widgets[] = PatientTimelineWidget::class;
        $widgets[] = RecentPatientsWidget::class;

        return $widgets;
    }

    protected function getFooterWidgets(): array
    {
        return [
            // PendingFulfillmentsWidget::class,
        ];
    }
}
