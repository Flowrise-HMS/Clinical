<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Pages;

use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Modules\Clinical\Classes\Services\WardBoardService;
use Modules\Clinical\Classes\Services\WardInchargeNoteService;
use Modules\Clinical\Enums\WardNotePriority;
use Modules\Clinical\Enums\WardNoteStatus;
use Modules\Clinical\Enums\WardNoteType;
use Modules\Clinical\Enums\WardShift;
use Modules\Clinical\Models\WardInchargeNote;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Classes\Services\WardAccessService;
use Modules\Core\Enums\SidebarGroup;
use Modules\Core\Models\Location;

class WardInchargeNotesPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Incharge Notes & Shift Handovers';

    protected static ?string $navigationLabel = 'Incharge Notes';

    protected static ?string $cluster = null;

    protected static string|\BackedEnum|null $navigationIcon = LucideIcon::BookOpenText;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::Workspaces;

    protected static ?int $navigationSort = 17;

    protected static ?string $slug = 'ward-notes';

    protected string $view = 'clinical::clinical.workspace.pages.ward-incharge-notes';

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

        return $user->can('ViewAny WardInchargeNote')
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
            Action::make('new_note')
                ->label(__('New Incharge Note / Handover'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->slideOver()
                ->modalWidth('2xl')
                ->schema([
                    Select::make('location_id')
                        ->label(__('Ward'))
                        ->options($this->wardOptions)
                        ->default($this->wardId)
                        ->required(),
                    Select::make('note_type')
                        ->label(__('Note type'))
                        ->options(WardNoteType::class)
                        ->default(WardNoteType::SHIFT_HANDOVER->value)
                        ->live()
                        ->required(),
                    Select::make('shift')
                        ->label(__('Shift'))
                        ->options(WardShift::class)
                        ->visible(fn (Get $get): bool => $get('note_type') === WardNoteType::SHIFT_HANDOVER->value),
                    DatePicker::make('note_date')
                        ->label(__('Date'))
                        ->default(now()->toDateString())
                        ->required(),
                    Select::make('priority')
                        ->label(__('Priority'))
                        ->options(WardNotePriority::class)
                        ->default(WardNotePriority::NORMAL->value)
                        ->required(),
                    TextInput::make('title')
                        ->label(__('Title / Summary'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('body')
                        ->label(__('Details / Handover Report'))
                        ->rows(4)
                        ->required(),
                    Checkbox::make('emergency_trolley_checked')
                        ->label(__('Emergency trolley / resuscitation tray checked'))
                        ->visible(fn (Get $get): bool => $get('note_type') === WardNoteType::SHIFT_HANDOVER->value),
                    Checkbox::make('controlled_drugs_tallied')
                        ->label(__('Controlled / DDA drugs count tallied'))
                        ->visible(fn (Get $get): bool => $get('note_type') === WardNoteType::SHIFT_HANDOVER->value),
                    Checkbox::make('equipment_oxygen_checked')
                        ->label(__('Ward equipment & oxygen supply checked'))
                        ->visible(fn (Get $get): bool => $get('note_type') === WardNoteType::SHIFT_HANDOVER->value),
                    Toggle::make('is_pinned')
                        ->label(__('Pin notice for ward staff'))
                        ->default(false),
                    Toggle::make('escalate_to_admin')
                        ->label(__('Escalate to Nursing Admin / Management'))
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $ward = Location::withoutGlobalScope('branch')->findOrFail($data['location_id']);

                    $payload = null;
                    if (($data['note_type'] ?? null) === WardNoteType::SHIFT_HANDOVER->value) {
                        $payload = [
                            'emergency_trolley_checked' => (bool) ($data['emergency_trolley_checked'] ?? false),
                            'controlled_drugs_tallied' => (bool) ($data['controlled_drugs_tallied'] ?? false),
                            'equipment_oxygen_checked' => (bool) ($data['equipment_oxygen_checked'] ?? false),
                        ];
                    }

                    app(WardInchargeNoteService::class)->create($ward, [
                        'note_type' => $data['note_type'],
                        'shift' => $data['shift'] ?? null,
                        'note_date' => $data['note_date'],
                        'priority' => $data['priority'],
                        'title' => $data['title'],
                        'body' => $data['body'],
                        'structured_payload' => $payload,
                        'is_pinned' => (bool) ($data['is_pinned'] ?? false),
                        'escalated_to_role' => ! empty($data['escalate_to_admin']) ? 'super_admin' : null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title(__('Incharge note saved'))
                        ->send();

                    $this->resetTable();
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
                WardInchargeNote::query()
                    ->with(['ward', 'author', 'acknowledgedBy', 'resolvedBy'])
                    ->when(filled($this->wardId), fn ($q) => $q->where('location_id', $this->wardId))
                    ->when(filled($this->branchId), fn ($q) => $q->where('branch_id', $this->branchId))
                    ->orderByDesc('is_pinned')
                    ->orderByDesc('note_date')
                    ->orderByDesc('created_at')
            )
            ->columns([
                IconColumn::make('is_pinned')
                    ->label(__('Pin'))
                    ->boolean()
                    ->trueIcon('heroicon-s-bookmark')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('primary')
                    ->falseColor('gray'),
                TextColumn::make('note_date')
                    ->label(__('Date'))
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('ward.name')
                    ->label(__('Ward'))
                    ->searchable(),
                TextColumn::make('note_type')
                    ->label(__('Type'))
                    ->badge(),
                TextColumn::make('shift')
                    ->label(__('Shift'))
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('priority')
                    ->badge(),
                TextColumn::make('title')
                    ->label(__('Summary'))
                    ->description(fn (WardInchargeNote $record): string => str($record->body)->limit(90)->toString())
                    ->searchable()
                    ->wrap(),
                TextColumn::make('author.name')
                    ->label(__('Logged By')),
                TextColumn::make('acknowledgedBy.name')
                    ->label(__('Acknowledged By'))
                    ->placeholder('—')
                    ->description(fn (WardInchargeNote $record): ?string => $record->acknowledged_at?->format('d M, H:i')),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('note_type')->options(WardNoteType::class),
                SelectFilter::make('status')->options(WardNoteStatus::class),
                SelectFilter::make('priority')->options(WardNotePriority::class),
            ])
            ->actions([
                Action::make('acknowledge')
                    ->label(__('Acknowledge'))
                    ->icon('heroicon-m-check-circle')
                    ->color('info')
                    ->visible(fn (WardInchargeNote $record): bool => $record->acknowledged_at === null)
                    ->action(function (WardInchargeNote $record): void {
                        app(WardInchargeNoteService::class)->acknowledge($record);

                        Notification::make()
                            ->success()
                            ->title(__('Handover / note acknowledged'))
                            ->send();
                    }),
                Action::make('resolve')
                    ->label(__('Resolve'))
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->visible(fn (WardInchargeNote $record): bool => $record->status !== WardNoteStatus::RESOLVED)
                    ->schema([
                        Textarea::make('resolution_notes')
                            ->label(__('Resolution notes'))
                            ->rows(2),
                    ])
                    ->action(function (WardInchargeNote $record, array $data): void {
                        app(WardInchargeNoteService::class)->resolve($record, $data['resolution_notes'] ?? null);

                        Notification::make()
                            ->success()
                            ->title(__('Note marked resolved'))
                            ->send();
                    }),
                Action::make('toggle_pin')
                    ->label(fn (WardInchargeNote $record): string => $record->is_pinned ? __('Unpin') : __('Pin'))
                    ->icon('heroicon-m-bookmark')
                    ->color('gray')
                    ->action(function (WardInchargeNote $record): void {
                        app(WardInchargeNoteService::class)->togglePin($record);
                    }),
            ])
            ->emptyStateHeading(__('No ward incharge notes recorded'))
            ->emptyStateDescription(__('Record shift handovers, daily ward affairs, incidents, and directives here.'));
    }
}
