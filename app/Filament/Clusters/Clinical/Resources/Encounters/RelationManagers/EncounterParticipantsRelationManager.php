<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;

class EncounterParticipantsRelationManager extends RelationManager
{
    protected static string $relationship = 'participants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Staff Member')
                    ->relationship('user', 'name', fn ($query) => $query->where('is_active', true))
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->name ?? $record?->email ?? 'Unknown')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('role')
                    ->options(ParticipantRole::class)
                    ->required()
                    ->searchable(),

                DateTimePicker::make('joined_at')
                    ->label('Joined At')
                    ->default(now()),

                Select::make('status')
                    ->options(ParticipantStatus::class)
                    ->default(ParticipantStatus::ACTIVE)
                    ->required(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Staff Member')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->sortable(),

                TextColumn::make('joined_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('left_at')
                    ->label('Left')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Present'),

                IconColumn::make('status')
                    ->label('Status')
                    ->icon(fn ($state) => $state === ParticipantStatus::ACTIVE ? 'heroicon-s-check-circle' : 'heroicon-m-x-circle')
                    ->color(fn ($state) => $state === ParticipantStatus::ACTIVE ? 'success' : 'gray'),

                TextColumn::make('shift_duration')
                    ->label('Duration')
                    ->getStateUsing(fn ($record) => $record->shift_duration ? $record->shift_duration.' min' : '-'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(ParticipantRole::class)
                    ->label('Role'),

                SelectFilter::make('status')
                    ->options(ParticipantStatus::class)
                    ->label('Status'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                // Bulk actions disabled for participants
            ]);
    }
}
