<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Modules\Clinical\Models\CarePlanIntervention;

class CarePlanInterventionsTable
{
    /**
     * @return array<int, TextColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('description')
                ->label('Intervention')
                ->wrap()
                ->searchable(),
            TextColumn::make('order.instruction')
                ->label('Order')
                ->limit(40)
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('order.diagnosis.label')
                ->label('Diagnosis')
                ->state(fn (CarePlanIntervention $record): ?string => $record->order?->diagnosis?->displayLabel())
                ->limit(40)
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('performedBy.name')
                ->label('Performed by')
                ->placeholder('—'),
            TextColumn::make('performed_at')
                ->label('Performed')
                ->dateTime()
                ->sortable(),
            TextColumn::make('notes')
                ->limit(40)
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
