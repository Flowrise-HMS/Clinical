<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\NoteType;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\Schemas\ClinicalNoteInfolist;

class ClinicalNotesTable
{
    public static function configure(Table $table): Table
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
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->name ?? $record?->email ?? 'Unknown')
                    ->label('Author')
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->schema(fn (Schema $schema) => ClinicalNoteInfolist::configure($schema))
                    ->slideOver(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
