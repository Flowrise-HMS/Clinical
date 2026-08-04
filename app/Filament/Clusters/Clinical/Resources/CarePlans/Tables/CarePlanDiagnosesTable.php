<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Modules\Clinical\Models\CarePlanDiagnosis;

class CarePlanDiagnosesTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('diagnosis')
                ->label('Diagnosis')
                ->state(fn (CarePlanDiagnosis $record): string => $record->displayLabel())
                ->wrap()
                ->description(fn (CarePlanDiagnosis $record): ?string => $record->composed_statement !== $record->displayLabel()
                    ? $record->composed_statement
                    : null),
            TextColumn::make('problem.label')->label('Problem')->toggleable(),
            TextColumn::make('orders_count')
                ->label('Orders')
                ->state(fn (CarePlanDiagnosis $record): int => $record->orders->count())
                ->badge()
                ->color(fn (CarePlanDiagnosis $record): string => $record->orders->count() < 3 ? 'warning' : 'success'),
            TextColumn::make('recorded_at')->label('Recorded')->dateTime()->sortable(),
        ];
    }
}
