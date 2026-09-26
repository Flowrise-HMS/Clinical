<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\TriageAssessment;
use Modules\Core\Classes\Services\BranchService;

/**
 * Patients waiting to be seen, in SATS order: Red first, then anyone not yet
 * triaged (unknown acuity), then Orange, Yellow and Green; within a colour the
 * longest wait comes first. Rows past their SATS target time are flagged.
 */
class TriageQueueWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = 'Triage queue';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('View Encounter') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getTableQuery())
            ->columns($this->getTableColumns())
            ->recordActions([
                Action::make('open')
                    ->label(__('Open'))
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Encounter $record): string => ClinicalWorkspace::getUrl(['patientId' => $record->patient_id])),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE
                    WHEN latest_triage_category = 'red' THEN 0
                    WHEN latest_triage_category IS NULL THEN 1
                    WHEN latest_triage_category = 'orange' THEN 2
                    WHEN latest_triage_category = 'yellow' THEN 3
                    WHEN latest_triage_category = 'green' THEN 4
                    ELSE 5 END")
                ->orderByRaw('COALESCE(latest_triaged_at, encounters.admitted_at, encounters.created_at)'))
            ->defaultKeySort(false)
            ->poll('30s')
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('Nobody is waiting'))
            ->emptyStateDescription(__('Arrived and triaged patients in this branch appear here until they are seen.'))
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }

    protected function getTableQuery(): Builder
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();
        $since = now()->subDay();

        $latest = fn (string $column) => TriageAssessment::query()
            ->select($column)
            ->whereColumn('triage_assessments.encounter_id', 'encounters.id')
            ->orderByDesc('triaged_at')
            ->limit(1);

        return Encounter::query()
            ->select('encounters.*')
            ->addSelect([
                'latest_triage_category' => $latest('final_category'),
                'latest_tews_score' => $latest('tews_score'),
                'latest_triaged_at' => $latest('triaged_at'),
            ])
            ->withCasts(['latest_triaged_at' => 'datetime'])
            ->with('patient')
            ->whereIn('encounters.status', [EncounterStatus::ARRIVED->value, EncounterStatus::TRIAGED->value])
            ->where('encounters.type', '!=', EncounterType::INPATIENT->value)
            ->where(fn (Builder $query): Builder => $query
                ->where('encounters.admitted_at', '>=', $since)
                ->orWhere('encounters.created_at', '>=', $since))
            ->when(filled($branchId), fn (Builder $query): Builder => $query->where('encounters.branch_id', $branchId));
    }

    /**
     * @return array<int, mixed>
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('latest_triage_category')
                ->label(__('Triage'))
                ->state(fn (Encounter $record): ?TriageCategory => enum_try_from(TriageCategory::class, $record->latest_triage_category))
                ->formatStateUsing(fn (TriageCategory $state): string => $state->shortLabel())
                ->badge()
                ->placeholder(__('Awaiting triage')),
            TextColumn::make('patient_name')
                ->label(__('Patient'))
                ->state(fn (Encounter $record): ?string => $record->patient?->full_name)
                ->description(fn (Encounter $record): ?string => $record->patient?->mrn)
                ->url(fn (Encounter $record): string => ClinicalWorkspace::getUrl(['patientId' => $record->patient_id]))
                ->weight('medium'),
            TextColumn::make('latest_tews_score')
                ->label(__('TEWS'))
                ->placeholder('-'),
            TextColumn::make('chief_complaint')
                ->label(__('Complaint'))
                ->limit(40)
                ->placeholder('-')
                ->toggleable(),
            TextColumn::make('waiting')
                ->label(__('Waiting'))
                ->state(fn (Encounter $record): string => $this->waitingSince($record)->diffForHumans(short: true, syntax: Carbon::DIFF_ABSOLUTE))
                ->description(fn (Encounter $record): ?string => $this->targetDescription($record))
                ->color(fn (Encounter $record): ?string => $this->isOverdue($record) ? 'danger' : null)
                ->weight(fn (Encounter $record): ?string => $this->isOverdue($record) ? 'bold' : null),
            TextColumn::make('status')
                ->label(__('Status'))
                ->badge()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function waitingSince(Encounter $record): Carbon
    {
        return $record->latest_triaged_at ?? $record->admitted_at ?? $record->created_at;
    }

    protected function targetAt(Encounter $record): ?Carbon
    {
        $category = enum_try_from(TriageCategory::class, $record->latest_triage_category);
        $minutes = $category?->targetMinutes();

        if ($minutes === null || $record->latest_triaged_at === null) {
            return null;
        }

        return $record->latest_triaged_at->copy()->addMinutes($minutes);
    }

    protected function isOverdue(Encounter $record): bool
    {
        $target = $this->targetAt($record);

        return $target !== null && now()->greaterThan($target);
    }

    protected function targetDescription(Encounter $record): ?string
    {
        $target = $this->targetAt($record);

        if ($target === null) {
            return $record->latest_triage_category === null ? __('Triage now') : null;
        }

        return $this->isOverdue($record)
            ? __('Overdue since :time', ['time' => $target->format('H:i')])
            : __('See by :time', ['time' => $target->format('H:i')]);
    }
}
