<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VitalSignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('recorded_at')->label('Date/Time')->dateTime()->sortable(),
                TextColumn::make('blood_pressure')
                    ->suffix('mmHg')
                    ->color(fn ($record) => $record->isAbnormalBloodPressure() ? 'text-warning-600 font-medium' : 'text-gray-900'),
                TextColumn::make('heart_rate')->suffix('bpm'),
                TextColumn::make('temperature'),
                TextColumn::make('spo2'),
                TextColumn::make('respiratory_rate'),
                TextColumn::make('recordedBy.name'),
            ])
            ->emptyStateHeading('No vitals recorded')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
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
