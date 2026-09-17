<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Policies\EncounterPolicy;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Enums\LocationType;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Models\Location;

/**
 * Ward-facing queue of admission requests waiting on a decision. Accepting
 * confirms the bed and admits the patient; rejecting records the reason and
 * hands the encounter back to the requesting clinician.
 */
class PendingAdmissionsWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Pending admissions';

    protected int $sorting = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('Update Encounter') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            ->recordActions($this->getTableActions())
            ->defaultSort('requested_at')
            ->poll('60s')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No admissions waiting for a decision')
            ->emptyStateDescription('Requests from clinicians in this branch appear here until a ward accepts or rejects them.')
            ->emptyStateIcon('heroicon-o-building-office');
    }

    protected function getTableQuery(): Builder
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();

        return AdmissionRequest::query()
            ->pending()
            ->when(
                filled($branchId),
                fn (Builder $query): Builder => $query->where('branch_id', $branchId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['encounter.patient', 'patient', 'requestedWard', 'requestedBed', 'requester']);
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTableColumns(): array
    {
        return [
            ClientIdentityColumn::make(label: __('Patient'))
                ->placeholder(__('Unknown patient'))
                ->action(
                    Action::make('openPatient')
                        ->action(fn (AdmissionRequest $record) => $this->dispatch('select-patient', patientId: $record->patient_id))
                ),
            TextColumn::make('encounter.encounter_number')
                ->label(__('Encounter'))
                ->searchable()
                ->toggleable(),
            TextColumn::make('encounter.type')
                ->label(__('Visit type'))
                ->badge()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('encounter.priority')
                ->label(__('Priority'))
                ->badge()
                ->toggleable(),
            TextColumn::make('encounter.chief_complaint')
                ->label(__('Chief complaint'))
                ->limit(40)
                ->tooltip(fn (AdmissionRequest $record): ?string => $record->encounter?->chief_complaint)
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('requestedWard.name')
                ->label(__('Ward'))
                ->sortable()
                ->searchable()
                ->toggleable(),
            TextColumn::make('requestedBed.name')
                ->label(__('Preferred bed'))
                ->placeholder(__('Any'))
                ->toggleable(),
            TextColumn::make('requester.name')
                ->label(__('Requested by'))
                ->searchable()
                ->toggleable(),
            TextColumn::make('requested_at')
                ->label(__('Requested'))
                ->since()
                ->dateTimeTooltip()
                ->sortable()
                ->toggleable(),
            TextColumn::make('notes')
                ->label(__('Notes'))
                ->limit(40)
                ->tooltip(fn (AdmissionRequest $record): ?string => $record->notes)
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTableFilters(): array
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();

        return [
            SelectFilter::make('requested_ward_id')
                ->label(__('Ward'))
                ->options(fn (): array => Location::query()
                    ->where('type', LocationType::ROOM)
                    ->when($branchId, fn (Builder $query): Builder => $query->where('branch_id', $branchId))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->searchable()
                ->multiple(),
            SelectFilter::make('priority')
                ->label(__('Priority'))
                ->options(EncounterPriority::class)
                ->query(fn (Builder $query, array $data): Builder => $query->when(
                    filled($data['value'] ?? null),
                    fn (Builder $query): Builder => $query->whereHas(
                        'encounter',
                        fn (Builder $encounterQuery): Builder => $encounterQuery->where('priority', $data['value']),
                    ),
                )),
            SelectFilter::make('requested_by')
                ->label(__('Requested by'))
                ->relationship('requester', 'name')
                ->searchable()
                ->preload(),
            Filter::make('requested_today')
                ->label(__('Requested today'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereDate('requested_at', now()->toDateString())),
            Filter::make('has_preferred_bed')
                ->label(__('Has preferred bed'))
                ->toggle()
                ->query(fn (Builder $query): Builder => $query->whereNotNull('requested_bed_id')),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTableActions(): array
    {
        return [
            Action::make('accept')
                ->label(__('Accept'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->button()
                ->size('xs')
                ->visible(fn (AdmissionRequest $record): bool => $this->canDecide($record))
                ->modalHeading(__('Accept admission'))
                ->modalDescription(fn (AdmissionRequest $record): string => EncounterActions::pendingRequestSummary($record->encounter))
                ->modalSubmitActionLabel(__('Admit to bed'))
                ->slideOver()
                ->schema(fn (AdmissionRequest $record): array => EncounterActions::acceptAdmissionSchema($record->encounter))
                ->action(function (AdmissionRequest $record, array $data): void {
                    if (! $this->canDecide($record)) {
                        Notification::make()->title(__('Not authorized'))->danger()->send();

                        return;
                    }

                    try {
                        app(AdtService::class)->acceptAdmission(
                            $record,
                            $data['bed_id'],
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title(__('Admission accepted — patient admitted to bed'))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Accept failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reject')
                ->label(__('Reject'))
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->button()
                ->size('xs')
                ->visible(fn (AdmissionRequest $record): bool => $this->canDecide($record))
                ->modalHeading(__('Reject admission'))
                ->modalDescription(fn (AdmissionRequest $record): string => EncounterActions::pendingRequestSummary($record->encounter))
                ->modalSubmitActionLabel(__('Reject'))
                ->schema(EncounterActions::rejectAdmissionSchema())
                ->action(function (AdmissionRequest $record, array $data): void {
                    if (! $this->canDecide($record)) {
                        Notification::make()->title(__('Not authorized'))->danger()->send();

                        return;
                    }

                    try {
                        app(AdtService::class)->rejectAdmission($record, $data['reason']);

                        Notification::make()->title(__('Admission rejected'))->warning()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('Reject failed'))->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    protected function canDecide(AdmissionRequest $record): bool
    {
        $user = Auth::user();

        return $user !== null
            && $record->isPending()
            && $record->encounter !== null
            && app(EncounterPolicy::class)->update($user, $record->encounter);
    }
}
