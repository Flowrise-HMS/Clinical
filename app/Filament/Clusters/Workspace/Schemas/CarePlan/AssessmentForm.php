<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

class AssessmentForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->maxLength(255)
                        ->label('Nursing problem'),
                    TextInput::make('priority')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(5)
                        ->label('Priority'),
                ]),
            Textarea::make('description')
                ->rows(3)
                ->label('Assessment findings'),
            Textarea::make('strength')
                ->required()
                ->rows(2)
                ->label('Patient strength')
                ->helperText('Record a strength for every identified nursing problem.'),
        ];
    }
}
