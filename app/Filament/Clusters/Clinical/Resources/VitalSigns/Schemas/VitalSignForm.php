<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VitalSignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                Grid::make(2)
                    ->schema([
                        Select::make('patient_id')
                            ->relationship('patient', 'mrn')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record ? $record->full_name : 'Select patient')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Patient'),

                        Select::make('encounter_id')
                            ->relationship('encounter', 'encounter_number')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record ? "{$record->encounter_number} - {$record->display_name}" : 'Select encounter')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Encounter'),
                    ]),

            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Vital Signs')
                ->description('Record patient vital signs')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('systolic_bp')
                                ->label('Systolic BP')
                                ->suffix('mmHg')
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state && $state > 140) {
                                        $set('bp_warning', true);
                                    } else {
                                        $set('bp_warning', false);
                                    }
                                }),

                            TextInput::make('diastolic_bp')
                                ->label('Diastolic BP')
                                ->suffix('mmHg')
                                ->numeric(),

                            TextInput::make('heart_rate')
                                ->label('Heart Rate')
                                ->suffix('bpm')
                                ->numeric(),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('temperature')
                                ->label('Temperature')
                                ->suffix('°C')
                                ->numeric()
                                ->step(0.1),

                            TextInput::make('spo2')
                                ->label('SpO2')
                                ->suffix('%')
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state && $state < 94) {
                                        $set('spo2_warning', true);
                                    } else {
                                        $set('spo2_warning', false);
                                    }
                                }),

                            TextInput::make('respiratory_rate')
                                ->label('Respiratory Rate')
                                ->suffix('/min')
                                ->numeric(),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('weight')
                                ->label('Weight')
                                ->suffix('kg')
                                ->numeric()
                                ->step(0.1),

                            TextInput::make('height')
                                ->label('Height')
                                ->suffix('cm')
                                ->numeric()
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                    $weight = $get('weight');
                                    if ($state && $weight) {
                                        $bmi = $weight / (($state / 100) ** 2);
                                        $set('calculated_bmi', round($bmi, 1));
                                    }
                                }),
                        ]),

                    TextInput::make('calculated_bmi')
                        ->label('BMI (calculated)')
                        ->numeric()
                        ->readOnly(),
                ]),
        ];
    }
}
