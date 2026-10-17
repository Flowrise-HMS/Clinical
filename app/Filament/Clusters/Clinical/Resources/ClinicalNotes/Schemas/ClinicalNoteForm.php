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
                            ->label('Patient')
                            ->helperText(__('Select the patient this clinical note belongs to.')),

                        Select::make('encounter_id')
                            ->relationship('encounter', 'encounter_number')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record ? "{$record->encounter_number} - {$record->display_name}" : 'Select encounter')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('Encounter')
                            ->helperText(__('Optionally link this note to an encounter visit.')),
                    ]),

            ], self::quickElements(), ));
    }

    public static function quickElements()
    {
        return [
            Section::make('Clinical Note')
                ->description('Document progress notes, consultations, and other clinical documentation for this patient.')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Select::make('note_type')
                                ->label('Note Type')
                                ->options(NoteType::class)
                                ->searchable()
                                ->required()
                                ->preload()
                                ->helperText(__('Categorize the purpose of this note (e.g., Progress, Consultation, Nursing).')),

                            Select::make('status')
                                ->label('Status')
                                ->options(NoteStatus::class)
                                ->default(NoteStatus::DRAFT)
                                ->required()
                                ->helperText(__('Draft notes remain editable; Final notes represent completed documentation.')),
                        ]),

                    TextInput::make('subject')
                        ->label('Subject')
                        ->maxLength(255)
                        ->helperText(__('Brief summary or title describing the encounter or topic of this note.')),

                    RichEditor::make('content')
                        ->label('Note Content')
                        ->fileAttachmentsDisk('local')
                        ->fileAttachmentsDirectory('notes')
                        ->helperText(__('Detailed clinical observations, subjective/objective findings, assessment, and treatment plan.')),
                ]),
        ];
    }
}
