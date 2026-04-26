<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                Section::make('Task Information')
                    ->description('Basic task details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->options(TaskStatus::class)
                                    ->required()
                                    ->label('Status'),

                                Select::make('outcome')
                                    ->options(TaskOutcome::class)
                                    ->label('Outcome'),
                            ]),
                    ]),
            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Performance')
                ->description('Task execution details')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('duration_minutes')
                                ->numeric()
                                ->label('Duration (minutes)'),
                        ]),

                    Repeater::make('results')
                        ->label('Results')
                        ->schema([
                            TextInput::make('key')
                                ->label('Field')
                                ->required(),
                            TextInput::make('value')
                                ->label('Value')
                                ->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(2),
                ]),
        ];
    }
}