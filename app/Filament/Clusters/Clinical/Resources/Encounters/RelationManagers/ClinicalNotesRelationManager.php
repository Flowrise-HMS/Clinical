<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;

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
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('note_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_signed')
                    ->label('Signed')
                    ->icon(fn ($state) => $state ? 'heroicon-s-check-circle' : 'heroicon-m-minus-circle')
                    ->color(fn ($state) => $state ? 'success' : 'gray'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('signed_at')
                    ->label('Signed At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not signed'),
            ])
            ->filters([
                SelectFilter::make('note_type')
                    ->options(NoteType::class)
                    ->label('Note Type'),

                SelectFilter::make('status')
                    ->options(NoteStatus::class)
                    ->label('Status'),

                SelectFilter::make('author')
                    ->relationship('author', 'name')
                    ->label('Author')
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Note'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
