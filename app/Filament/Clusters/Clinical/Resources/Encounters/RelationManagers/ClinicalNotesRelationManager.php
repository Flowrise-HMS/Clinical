<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Tables\ClinicalNotesTable;

class ClinicalNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'clinicalNotes';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Note Details')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Select::make('note_type')
                                    ->options(NoteType::class)
                                    ->required()
                                    ->searchable()
                                    ->label('Note Type'),

                                TextInput::make('subject')
                                    ->label('Subject/Title')
                                    ->maxLength(255),

                                Select::make('status')
                                    ->options(NoteStatus::class)
                                    ->default(NoteStatus::DRAFT)
                                    ->required()
                                    ->label('Status'),
                            ]),
                    ]),

                Section::make('Note Content')
                    ->schema([
                        RichEditor::make('content')
                            ->label('Clinical Note')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->fileAttachmentsDisk('local')
                            ->fileAttachmentsDirectory('clinical-notes')
                            ->fileAttachmentsVisibility('private'),
                    ]),

                Section::make('Signing')
                    ->schema([
                        Checkbox::make('is_signed')
                            ->label('Signed')
                            ->disabled()
                            ->dehydrated(false),

                        DateTimePicker::make('signed_at')
                            ->label('Signed At')
                            ->disabled(),

                        TextInput::make('signedBy.name')
                            ->label('Signed By')
                            ->disabled(),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return ClinicalNotesTable::configure($table)
            ->headerActions([
                CreateAction::make()
                    ->label('Add Note'),
            ]);
    }
}
