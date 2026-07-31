<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Modules\Clinical\Enums\RoutineCareItem;

class RoutineCareForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            Select::make('item')
                ->options(RoutineCareItem::class)
                ->required()
                ->label('Routine care item'),
            Toggle::make('not_applicable')
                ->live()
                ->label('Not applicable'),
            Textarea::make('specification')
                ->required(fn (callable $get): bool => ! $get('not_applicable'))
                ->rows(2)
                ->label('Care instruction'),
            Textarea::make('notes')
                ->rows(2)
                ->label('Notes'),
        ];
    }
}
