<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Enums\CoverageType;

class EncounterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                Section::make('Patient Information')
                    ->description('Select an existing patient or register a walk-in guest')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('patient_id')
                                    ->relationship('patient', 'mrn')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record ? $record->full_name : 'Select patient')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Patient (Existing)'),

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

            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Encounter Details')
                ->description('Basic encounter information')
                ->schema([
                    Grid::make(4)
                        ->schema([
                            Select::make('type')
                                ->options(EncounterType::class)
                                ->default('outpatient')
                                ->required()
                                ->live()
                                ->label('Encounter Type'),

                            Select::make('priority')
                                ->options(EncounterPriority::class)
                                ->default('routine')
                                ->required()
                                ->label('Priority'),

                            Select::make('status')
                                ->options(EncounterStatus::class)
                                ->default('planned')
                                ->required()
                                ->label('Status'),

                            Select::make('coverage_type')
                                ->options([
                                    'nhis' => 'NHIS',
                                    'private' => 'Private Insurance',
                                    'none' => 'Cash',
                                ])
                                ->native(false)
                                ->nullable()
                                ->label('Coverage Type'),
                        ]),

                    Fieldset::make('Clinical Information')
                        ->schema([
                            // Textarea::make('chief_complaint')
                            //     ->label('Chief Complaint')
                            //     ->helperText('Primary reason for visit')
                            //     ->rows(2)
                            //     ->columnSpanFull(),

                            RichEditor::make('notes')
                                ->label('Clinical Notes')
                                ->toolbarButtons([
                                    'attachFiles',
                                    'bold',
                                    'bulletList',
                                    'italic',
                                    'orderedList',
                                    'strike',
                                ])
                                ->fileAttachmentsDisk('local')
                                ->fileAttachmentsDirectory('encounters')
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Location & Assignment')
                ->description('Encounter location and assigned resources')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('branch_id')
                                ->relationship('branch', 'name')
                                ->required()
                                ->default(fn () => app(BranchService::class)->getDefaultBranchId())
                                ->label('Branch/ Facility'),

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
                ]),
        ];
    }
}
