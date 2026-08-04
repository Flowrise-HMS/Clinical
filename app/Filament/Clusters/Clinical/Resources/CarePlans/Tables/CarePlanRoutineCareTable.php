<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class CarePlanRoutineCareTable
{
    /**
     * @return array<int, TextColumn|IconColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('item')
                ->label('Item')
                ->formatStateUsing(fn ($state): string => $state?->getLabel() ?? (string) $state),
            IconColumn::make('not_applicable')
                ->label('N/A')
                ->boolean(),
            TextColumn::make('specification')->label('Instruction')->wrap()->limit(60),
            TextColumn::make('notes')->label('Notes')->limit(40)->toggleable(),
            TextColumn::make('specified_at')->label('Updated')->dateTime()->sortable(),
        ];
    }
}
