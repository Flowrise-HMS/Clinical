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
    /**
     * @return array<int, TextColumn|IconColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('note_type')
                ->label('Type')
                ->badge()
                ->sortable(),

            TextColumn::make('subject')
                ->label('Subject')
                ->searchable()
                ->limit(40)
                ->placeholder('—'),

            TextColumn::make('author.name')
                ->label('Author')
                ->searchable()
                ->sortable()
                ->placeholder('—'),

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
                ->placeholder('Not signed')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, SelectFilter>
     */
    public static function filters(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, ViewAction|EditAction|DeleteAction>
     */
    public static function recordActions(bool $includeMutations = true): array
    {
        $actions = [
            ViewAction::make()
                ->schema(fn (Schema $schema) => ClinicalNoteInfolist::configure($schema))
                ->slideOver(),
        ];

        if ($includeMutations) {
            $actions[] = EditAction::make();
            $actions[] = DeleteAction::make();
        }

        return $actions;
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->defaultSort('created_at', 'desc')
            ->columns(self::columns())
            ->filters(self::filters())
            ->recordActions(self::recordActions())
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
