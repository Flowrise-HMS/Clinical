<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Core\Classes\Services\BranchService;

class EncounterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Patient Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('patient_id')
                                    ->relationship('patient', 'full_name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Patient'),

                                TextInput::make('guest_name')
                                    ->label('Guest Name')
                                    ->helperText('For walk-in guests without patient record'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('guest_phone')
                                    ->label('Guest Phone')
                                    ->tel(),

                                TextInput::make('guest_email')
                                    ->label('Guest Email')
                                    ->email()
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Encounter Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('type')
                                    ->enum(EncounterType::class)
                                    ->options(EncounterType::class)
                                    ->default('outpatient')
                                    ->required()
                                    ->label('Encounter Type'),

                                Select::make('priority')
                                    ->enum(EncounterPriority::class)
                                    ->options(EncounterPriority::class)
                                    ->default('routine')
                                    ->required()
                                    ->label('Priority'),

                                Select::make('status')
                                    ->enum(EncounterStatus::class)
                                    ->options(EncounterStatus::class)
                                    ->default('planned')
                                    ->required()
                                    ->label('Status'),
                            ]),

                        Textarea::make('chief_complaint')
                            ->label('Chief Complaint')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('branch_id')
                                    ->relationship('branch', 'name')
                                    ->required()
                                    ->default(fn () => app(BranchService::class)->getDefaultBranchId())
                                    ->label('Branch'),

                                Select::make('location_id')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Current Location'),

                                Select::make('department_id')
                                    ->relationship('department', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Department'),
                            ]),

                        Select::make('bed_id')
                            ->relationship('bed', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Bed (Inpatient)')
                            ->visible(fn (callable $get) => $get('type') === 'inpatient'),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->collapsible(),
            ]);
    }
}
