<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Modules\Clinical\Models\CarePlanProblem;

class CarePlanProblemsTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('label')->label('Problem')->wrap()->searchable(),
            TextColumn::make('priority')->label('Priority')->badge(),
            TextColumn::make('strengths_count')
                ->label('Strengths')
                ->state(fn (CarePlanProblem $record): int => $record->strengths->count()),
            TextColumn::make('description')->label('Notes')->limit(40)->toggleable(),
        ];
    }
}
