<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\OnsetType;

class AllergyForm
{
    public static function configure(Schema $schema, bool $hidePatient = false): Schema
    {
        return $schema
            ->components(array_merge([
                Select::make('patient_id')
                    ->relationship('patient', 'mrn')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record ? $record->full_name : 'Select patient')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->hidden($hidePatient)
                    ->label('Patient'),

            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Allergy Information')
                ->schema([


                    Grid::make(3)
                        ->schema([
                            Select::make('allergen_type')
                                ->options(AllergenType::class)
                                ->required()
                                ->label('Allergen Type'),

                            TextInput::make('allergen_name')
                                ->required()
                                ->maxLength(255)
                                ->label('Allergen Name'),
                            TextInput::make('onset_age')
                                ->numeric()
                                ->suffix('years')
                                ->label('Onset Age'),
                            Select::make('severity')
                                ->options(AllergySeverity::class)
                                ->default(AllergySeverity::MILD->value)
                                ->required()
                                ->label('Severity'),

                            Select::make('verification_status')
                                ->options(AllergyVerificationStatus::class)
                                ->default(AllergyVerificationStatus::VERIFIED->value)
                                ->required()
                                ->label('Verification Status'),

                            Select::make('onset_type')
                                ->options(OnsetType::class)
                                ->default(OnsetType::ACUTE->value)
                                ->required()
                                ->label('Onset Type'),
                        ]),

                    Grid::make(2)
                        ->schema([
                             Textarea::make('reaction')
                                ->maxLength(1000)
                                ->columnSpanFull()
                                ->label('Reaction'),
                            Textarea::make('notes')
                                ->maxLength(2000)
                                ->columnSpanFull()
                                ->label('Notes'),
                        ]),
                ]),
        ];
    }
}
