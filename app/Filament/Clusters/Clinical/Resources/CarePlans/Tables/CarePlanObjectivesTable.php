<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanObjectivesTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
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
}
