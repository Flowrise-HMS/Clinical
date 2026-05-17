<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;

class ClinicalNoteForm
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

            ], self::quickElements(), ));
    }

    public static function quickElements()
    {
        return [
            Section::make('Clinical Note')
                ->description('Create a new clinical note')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('note_type')
                                ->label('Note Type')
                                ->options(NoteType::class)
                                ->required()
                                ->preload(),

                            Select::make('status')
                                ->label('Status')
                                ->options(NoteStatus::class)
                                ->default(NoteStatus::DRAFT)
                                ->required(),
                        ]),

                    TextInput::make('subject')
                        ->label('Subject')
                        ->maxLength(255),

                    RichEditor::make('content')
                        ->label('Note Content')

                        ->fileAttachmentsDisk('local')
                        ->fileAttachmentsDirectory('notes'),
                ]),
        ];
    }
}
