<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\PatientPosition;
use Modules\Clinical\Enums\SpO2Label;
use Modules\Clinical\Enums\SpO2Parameter;
use Modules\Clinical\Enums\VitalSignType;

class VitalSignsRelationManager extends RelationManager
{
    protected static string $relationship = 'vitalSigns';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Measurement Context')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('type')
                                    ->options(VitalSignType::class)
                                    ->default(VitalSignType::ROUTINE)
                                    ->required()
                                    ->label('Type'),

                                Select::make('position')
                                    ->options(PatientPosition::class)
                                    ->label('Patient Position'),

                                TextInput::make('measurement_location')
                                    ->label('Measurement Location'),

                                DateTimePicker::make('recorded_at')
                                    ->default(now())
                                    ->required()
                                    ->label('Recorded At'),
                            ]),
                    ]),

                Section::make('Blood Pressure & Heart Rate')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('systolic_bp')
                                    ->label('Systolic BP (mmHg)')
                                    ->numeric()
                                    ->step(1),

                                TextInput::make('diastolic_bp')
                                    ->label('Diastolic BP (mmHg)')
                                    ->numeric()
                                    ->step(1),

                                TextInput::make('heart_rate')
                                    ->label('Heart Rate (bpm)')
                                    ->numeric()
                                    ->step(1),

                                TextInput::make('respiratory_rate')
                                    ->label('Respiratory Rate (/min)')
                                    ->numeric()
                                    ->step(1),
                            ]),
                    ]),

                Section::make('Oxygen Saturation')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('spo2')
                                    ->label('SpO2 (%)')
                                    ->numeric()
                                    ->step(0.1)
                                    ->suffix('%'),

                                Select::make('spo2_label')
                                    ->options(SpO2Label::class)
                                    ->label('SpO2 Status'),

                                Select::make('spo2_parameter')
                                    ->options(SpO2Parameter::class)
                                    ->label('Oxygen Parameter'),
                            ]),
                    ]),

                Section::make('Temperature & Pain')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('temperature')
                                    ->label('Temperature (°C)')
                                    ->numeric()
                                    ->step(0.1),

                                TextInput::make('pain_level')
                                    ->label('Pain Level (0-10)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(10),

                                TextInput::make('fbs')
                                    ->label('FBS (mg/dL)')
                                    ->numeric()
                                    ->step(1),

                                TextInput::make('rbs')
                                    ->label('RBS (mg/dL)')
                                    ->numeric()
                                    ->step(1),
                            ]),
                    ]),

                Section::make('Anthropometric Measurements')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('weight')
                                    ->label('Weight (kg)')
                                    ->numeric()
                                    ->step(0.1),

                                TextInput::make('height')
                                    ->label('Height (cm)')
                                    ->numeric()
                                    ->step(0.1),

                                TextEntry::make('bmi_preview')
                                    ->label('BMI Preview')
                                    ->state(function ($get) {
                                        $weight = $get('weight');
                                        $height = $get('height');
                                        if ($weight && $height) {
                                            $heightInMeters = $height / 100;
                                            $bmi = round($weight / ($heightInMeters * $heightInMeters), 2);
                                            $category = match (true) {
                                                $bmi < 18.5 => 'Underweight',
                                                $bmi < 25 => 'Normal',
                                                $bmi < 30 => 'Overweight',
                                                default => 'Obese',
                                            };

                                            return "BMI: {$bmi} ({$category})";
                                        }

                                        return 'BMI will be calculated automatically';
                                    }),
                            ]),
                    ]),

                Section::make('Glasgow Coma Scale')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('gcs_eye')
                                    ->label('Eye (1-4)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4),

                                TextInput::make('gcs_verbal')
                                    ->label('Verbal (1-5)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5),

                                TextInput::make('gcs_motor')
                                    ->label('Motor (1-6)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(6),

                                TextEntry::make('gcs_total')
                                    ->label('GCS Total')
                                    ->state(function ($get) {
                                        $eye = $get('gcs_eye');
                                        $verbal = $get('gcs_verbal');
                                        $motor = $get('gcs_motor');
                                        if ($eye && $verbal && $motor) {
                                            return "GCS: {$eye} + {$verbal} + {$motor} = ".($eye + $verbal + $motor);
                                        }

                                        return 'Total will be calculated';
                                    }),
                            ]),
                    ]),

                Section::make('Intake & Output')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('intake')
                                    ->label('Intake (ml)')
                                    ->numeric()
                                    ->step(1),

                                TextInput::make('output')
                                    ->label('Output (ml)')
                                    ->numeric()
                                    ->step(1),
                            ]),
                    ]),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Clinical Notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recorded_at')
            ->defaultSort('recorded_at', 'desc')
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Recorded')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),

                TextColumn::make('blood_pressure')
                    ->label('BP')
                    ->getStateUsing(fn ($record) => $record->blood_pressure ?? '-'),

                TextColumn::make('heart_rate')
                    ->label('HR (bpm)'),

                TextColumn::make('temperature')
                    ->label('Temp (°C)'),

                TextColumn::make('spo2')
                    ->label('SpO2')
                    ->suffix('%'),

                TextColumn::make('bmi')
                    ->label('BMI'),

                TextColumn::make('gcs_total')
                    ->label('GCS')
                    ->getStateUsing(fn ($record) => $record->gcs_total ?? '-'),

                TextColumn::make('recordedBy.name')
                    ->label('Recorded By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(VitalSignType::class)
                    ->label('Type'),

                SelectFilter::make('position')
                    ->options(PatientPosition::class)
                    ->label('Position'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record Vitals'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
