<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanObjectivesTableWidget extends BaseTableWidget
{
    protected static bool $isDiscovered = false;

    protected static ?string $heading = null;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $carePlanId = null;

    protected function getTableQuery(): Builder
    {
        return CarePlanObjective::query()
            ->when(
                filled($this->carePlanId),
                fn (Builder $query): Builder => $query->whereHas(
                    'diagnosis',
                    fn (Builder $diagnosisQuery): Builder => $diagnosisQuery->where('care_plan_id', $this->carePlanId),
                ),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            )
            ->with(['diagnosis', 'evaluations' => fn ($query) => $query->latest('evaluated_at')])
            ->latest('created_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('description')
                ->label('Objective')
                ->wrap()
                ->searchable(),
            TextColumn::make('diagnosis.label')
                ->label('Diagnosis')
                ->state(fn (CarePlanObjective $record): ?string => $record->diagnosis?->displayLabel())
                ->limit(40)
                ->placeholder('—'),
            TextColumn::make('achievement_status')
                ->label('Achievement')
                ->badge()
                ->placeholder('—'),
            TextColumn::make('evaluations_count')
                ->label('Evaluations')
                ->state(fn (CarePlanObjective $record): int => $record->evaluations->count())
                ->badge(),
            TextColumn::make('latest_outcome')
                ->label('Latest outcome')
                ->state(fn (CarePlanObjective $record): ?string => $record->evaluations->first()?->outcome?->getLabel())
                ->badge()
                ->placeholder('Not evaluated'),
            TextColumn::make('target_date')
                ->label('Target')
                ->date()
                ->placeholder('—')
                ->toggleable(),
        ];
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No objectives recorded yet';
    }
}
