<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Clinical\Models\VitalSign;

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
                                ->required()
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
                                ->numeric()
                                ->required(),

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
                                ->suffix('cpm')
                                ->numeric(),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('weight')
                                ->label('Weight')
                                ->suffix('kg')
                                ->numeric()
                                ->step(0.1)
                                ->minValue(0.3)
                                ->maxValue(500)
                                ->live(debounce: 400)
                                ->afterStateUpdated(fn (Set $set, Get $get) => static::refreshCalculatedBmi($set, $get)),

                            TextInput::make('height')
                                ->label('Height')
                                ->suffix('cm')
                                ->helperText('In centimetres (1.70 m = 170 cm).')
                                ->numeric()
                                ->step(0.1)
                                ->minValue(20)
                                ->maxValue(272)
                                ->validationMessages(['min' => 'Enter the height in centimetres, not metres or feet.'])
                                ->live(debounce: 400)
                                ->afterStateUpdated(fn (Set $set, Get $get) => static::refreshCalculatedBmi($set, $get)),
                        ]),

                    TextInput::make('calculated_bmi')
                        ->label('BMI (calculated)')
                        ->suffix('kg/m²')
                        ->visible(fn (Get $get): bool => filled($get('weight')) && filled($get('height')))
                        ->helperText(fn (Get $get): ?string => VitalSign::bmiCategoryFor(is_numeric($get('calculated_bmi')) ? (float) $get('calculated_bmi') : null))
                        ->numeric()
                        ->readOnly(),
                ]),
        ];
    }

    /**
     * Weight (kg) and height (cm) drive the read-only BMI; clearing either clears it.
     */
    protected static function refreshCalculatedBmi(Set $set, Get $get): void
    {
        $set('calculated_bmi', VitalSign::calculateBmi($get('weight'), $get('height')));
    }
}
