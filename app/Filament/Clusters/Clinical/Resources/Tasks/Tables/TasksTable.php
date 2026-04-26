<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('requestItem.serviceRequest.request_number')
                    ->label('Request')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('requestItem.service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('requestItem.serviceRequest.patient.full_name')
                    ->label('Patient')
                    ->sortable()
                    ->placeholder('Guest'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                TextColumn::make('outcome')
                    ->label('Outcome')
                    ->badge(),

                TextColumn::make('duration_display')
                    ->label('Duration')
                    ->placeholder('-'),

                TextColumn::make('performedBy.name')
                    ->label('Performed By')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TaskStatus::class),

                SelectFilter::make('outcome')
                    ->options(TaskOutcome::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
