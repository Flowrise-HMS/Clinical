<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class EncounterDiagnosesTable
{
    /**
     * @return array<int, TextColumn|IconColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->sortable(),
            TextColumn::make('certainty')
                ->label('Certainty')
                ->badge()
                ->sortable(),
            IconColumn::make('is_new_case')
                ->label('New')
                ->boolean(),
            TextColumn::make('icd_code')
                ->label('Code')
                ->placeholder('—')
                ->searchable(),
            TextColumn::make('description')
                ->label('Diagnosis')
                ->wrap()
                ->searchable(),
            TextColumn::make('notes')
                ->label('Notes')
                ->limit(40)
                ->placeholder('—')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('orderedBy.name')
                ->label('Recorded by')
                ->placeholder('—')
                ->toggleable(),
            TextColumn::make('created_at')
                ->label('Recorded')
                ->dateTime()
                ->sortable(),
        ];
    }
}
