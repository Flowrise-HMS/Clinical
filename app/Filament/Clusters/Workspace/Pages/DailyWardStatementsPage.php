<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Services\DailyWardStatementService;
use Modules\Clinical\Classes\Services\WardBoardService;
use Modules\Clinical\Enums\WardStatementPeriod;
use Modules\Clinical\Enums\WardStatementStatus;
use Modules\Clinical\Models\DailyWardStatement;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Classes\Services\WardAccessService;
use Modules\Core\Enums\SidebarGroup;
use Modules\Core\Models\Location;

class DailyWardStatementsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Daily Ward Statements';

    protected static ?string $navigationLabel = 'Daily Ward Statements';

    protected static ?string $cluster = null;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::ClipboardCheck;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::Workspaces;

    protected static ?int $navigationSort = 16;

    protected static ?string $slug = 'ward-statements';

    protected string $view = 'clinical::clinical.workspace.pages.daily-ward-statements';

    #[Url(as: 'ward', except: '')]
    public ?string $wardId = null;

    public ?string $branchId = null;

    /**
     * @var array<string, string>
     */
    public array $wardOptions = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->can('ViewAny DailyWardStatement')
            || app(WardAccessService::class)->isAnyWardIncharge($user);
    }

    public function mount(): void
    {
        $this->branchId = app(BranchService::class)->getDefaultBranchId();
        $this->wardOptions = $this->branchId
            ? app(WardBoardService::class)->wardsForBranch($this->branchId)->all()
            : [];

        if ($this->wardId === null || ! array_key_exists($this->wardId, $this->wardOptions)) {
            $user = Auth::user();
            $assignedWardId = $user !== null
                ? app(WardAccessService::class)->defaultWardIdFor($user, $this->branchId)
                : null;

            $this->wardId = ($assignedWardId !== null && array_key_exists($assignedWardId, $this->wardOptions))
                ? $assignedWardId
                : array_key_first($this->wardOptions);
        }
    }

    public function updatedWardId(): void
    {
        $this->resetTable();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_statement')
                ->label(__('Generate / Refresh Statement'))
                ->icon('heroicon-o-calculator')
                ->color('primary')
                ->slideOver()
                ->modalWidth('lg')
                ->schema([
                    Select::make('location_id')
                        ->label(__('Ward'))
                        ->options($this->wardOptions)
                        ->default($this->wardId)
                        ->required(),
                    DatePicker::make('statement_date')
                        ->label(__('Statement date'))
                        ->default(now()->toDateString())
                        ->required(),
                    Select::make('period_type')
                        ->label(__('Period'))
                        ->options(WardStatementPeriod::class)
                        ->default(WardStatementPeriod::DAILY->value)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $ward = Location::withoutGlobalScope('branch')->findOrFail($data['location_id']);
                    $period = WardStatementPeriod::from($data['period_type']);

                    $statement = app(DailyWardStatementService::class)->generateOrRefreshDraft(
                        ward: $ward,
                        date: Carbon::parse($data['statement_date']),
                        period: $period,
                    );

                    Notification::make()
                        ->success()
                        ->title(__('Daily ward statement prepared'))
                        ->body(__('Remaining census: :count (Admissions: :adm, Discharges: :dis)', [
                            'count' => $statement->remaining_total,
                            'adm' => $statement->admissions_count,
                            'dis' => $statement->discharges_count,
                        ]))
                        ->send();

                    $this->resetTable();
                }),
            Action::make('branch_rollup')
                ->label(__('Hospital Bed State Rollup'))
                ->icon('heroicon-o-building-office-2')
                ->color('gray')
                ->slideOver()
                ->modalWidth('5xl')
                ->modalHeading(__('Hospital-Wide Daily Bed State Rollup (:date)', ['date' => now()->format('d M Y')]))
                ->modalSubmitAction(false)
                ->modalContent(function () {
                    $rows = $this->branchId
                        ? app(DailyWardStatementService::class)->branchRollup($this->branchId, now())
                        : collect();

                    return view('clinical::clinical.workspace.pages.partials.daily-ward-rollup', ['rows' => $rows]);
                }),
            Action::make('back_to_ward_board')
                ->label(__('Ward Board'))
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(fn (): string => WardBoard::getUrl(['ward' => $this->wardId])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DailyWardStatement::query()
                    ->with(['ward', 'preparedBy', 'submittedBy', 'reviewedBy'])
                    ->when(filled($this->wardId), fn ($q) => $q->where('location_id', $this->wardId))
                    ->when(filled($this->branchId), fn ($q) => $q->where('branch_id', $this->branchId))
                    ->orderByDesc('statement_date')
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('statement_date')
                    ->label(__('Date'))
                    ->date('D, d M Y')
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('ward.name')
                    ->label(__('Ward'))
                    ->searchable(),
                TextColumn::make('period_type')
                    ->label(__('Period'))
                    ->badge(),
                TextColumn::make('brought_forward')
                    ->label(__('B/F'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('admissions_count')
                    ->label(__('Adm'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('transfers_in_count')
                    ->label(__('Tr In'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('discharges_count')
                    ->label(__('Disch'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('transfers_out_count')
                    ->label(__('Tr Out'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('deaths_count')
                    ->label(__('Deaths'))
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->alignCenter(),
                TextColumn::make('lama_absconded_count')
                    ->label(__('LAMA'))
                    ->numeric()
                    ->alignCenter(),
                TextColumn::make('remaining_total')
                    ->label(__('Remaining'))
                    ->numeric()
                    ->weight('bold')
                    ->description(fn (DailyWardStatement $record): string => $record->occupancyRate().'% occ.')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('submittedBy.name')
                    ->label(__('Submitted By'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WardStatementStatus::class),
                SelectFilter::make('period_type')
                    ->options(WardStatementPeriod::class),
            ])
            ->actions([
                Action::make('review_submit')
                    ->label(__('Edit & Submit'))
                    ->icon('heroicon-m-paper-airplane')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('2xl')
                    ->visible(fn (DailyWardStatement $record): bool => $record->status->isEditable())
                    ->fillForm(fn (DailyWardStatement $record): array => [
                        'brought_forward' => $record->brought_forward,
                        'admissions_count' => $record->admissions_count,
                        'transfers_in_count' => $record->transfers_in_count,
                        'discharges_count' => $record->discharges_count,
                        'transfers_out_count' => $record->transfers_out_count,
                        'deaths_count' => $record->deaths_count,
                        'lama_absconded_count' => $record->lama_absconded_count,
                        'on_pass_count' => $record->on_pass_count,
                        'floor_or_extra_beds' => $record->floor_or_extra_beds,
                        'critically_ill_notes' => $record->critically_ill_notes,
                        'special_cases_notes' => $record->special_cases_notes,
                        'general_remarks' => $record->general_remarks,
                    ])
                    ->schema([
                        TextInput::make('brought_forward')->label(__('Brought Forward (Opening)'))->numeric()->required(),
                        TextInput::make('admissions_count')->label(__('Admissions'))->numeric()->required(),
                        TextInput::make('transfers_in_count')->label(__('Transfers In'))->numeric()->required(),
                        TextInput::make('discharges_count')->label(__('Discharges'))->numeric()->required(),
                        TextInput::make('transfers_out_count')->label(__('Transfers Out'))->numeric()->required(),
                        TextInput::make('deaths_count')->label(__('Deaths'))->numeric()->required(),
                        TextInput::make('lama_absconded_count')->label(__('LAMA / Absconded'))->numeric()->required(),
                        TextInput::make('on_pass_count')->label(__('On Pass'))->numeric()->required(),
                        TextInput::make('floor_or_extra_beds')->label(__('Floor / Extra Beds'))->numeric()->required(),
                        Textarea::make('critically_ill_notes')->label(__('Dangerously / Critically Ill List (DIL)'))->rows(2),
                        Textarea::make('special_cases_notes')->label(__('Special Cases (Police, Social Welfare, Maternal/Neonatal)'))->rows(2),
                        Textarea::make('general_remarks')->label(__('Ward Incharge Remarks'))->rows(2),
                    ])
                    ->action(function (DailyWardStatement $record, array $data): void {
                        app(DailyWardStatementService::class)->submit($record, $data);

                        Notification::make()
                            ->success()
                            ->title(__('Daily ward statement submitted & frozen'))
                            ->send();
                    }),
                Action::make('matron_review')
                    ->label(__('Review'))
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (DailyWardStatement $record): bool => $record->status === WardStatementStatus::SUBMITTED)
                    ->schema([
                        Textarea::make('review_notes')
                            ->label(__('Matron / Nursing Administration Review Notes'))
                            ->rows(3),
                    ])
                    ->action(function (DailyWardStatement $record, array $data): void {
                        app(DailyWardStatementService::class)->review($record, $data['review_notes'] ?? null);

                        Notification::make()
                            ->success()
                            ->title(__('Statement marked as reviewed'))
                            ->send();
                    }),
                Action::make('amend')
                    ->label(__('Amend'))
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->visible(fn (DailyWardStatement $record): bool => in_array($record->status, [WardStatementStatus::SUBMITTED, WardStatementStatus::REVIEWED], true))
                    ->schema([
                        Textarea::make('amendment_reason')
                            ->label(__('Reason for amendment'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (DailyWardStatement $record, array $data): void {
                        app(DailyWardStatementService::class)->reopenForAmendment($record, $data['amendment_reason']);

                        Notification::make()
                            ->warning()
                            ->title(__('Statement reopened for amendment'))
                            ->send();
                    }),
                Action::make('print')
                    ->label(__('Print'))
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (DailyWardStatement $record): string => route('clinical.ward-statements.print', $record), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading(__('No daily ward statements yet'))
            ->emptyStateDescription(__('Click "Generate / Refresh Statement" to auto-calculate today\'s bed state from ADT events.'));
    }
}
