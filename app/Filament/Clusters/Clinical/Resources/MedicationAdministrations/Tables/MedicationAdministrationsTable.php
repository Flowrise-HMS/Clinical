<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\MedicationAdministrations\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\MedicationAdministrationStatus;

class MedicationAdministrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),

                TextColumn::make('requestItem.service.name')
                    ->label('Medication / Drug')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('quantity_given')
                    ->label('Qty Given')
                    ->sortable(),

                TextColumn::make('doseUnit.label')
                    ->label('Dose Unit')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('administeredBy.name')
                    ->label('Administered By')
                    ->searchable()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ended_at')
                    ->label('Ended')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(MedicationAdministrationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('started_at', 'desc');
    }
}
