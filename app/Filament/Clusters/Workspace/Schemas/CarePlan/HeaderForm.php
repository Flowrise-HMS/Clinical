<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class HeaderForm
{
    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('title')
                ->maxLength(255)
                ->label('Care plan title'),
            Textarea::make('description')
                ->rows(3)
                ->label('Clinical summary'),
            DatePicker::make('discharge_date')
                ->label('Anticipated discharge date'),
            TextInput::make('operation')
                ->maxLength(255)
                ->label('Operation or procedure'),
            DatePicker::make('operation_date')
                ->label('Operation date'),
            Toggle::make('no_known_allergies')
                ->label('No known allergies')
                ->helperText('Confirm only after reviewing the patient allergy record.'),
        ];
    }
}
