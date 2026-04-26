<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Allergies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;

class AllergiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('patient.full_name')
                    ->sortable(),
                TextColumn::make('allergen_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('allergen_name')
                    ->searchable(),
                TextColumn::make('severity')
                    ->badge()
                    ->sortable(),
                TextColumn::make('verification_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('allergen_type')
                    ->options(AllergenType::class),
                SelectFilter::make('severity')
                    ->options(AllergySeverity::class),
                SelectFilter::make('verification_status')
                    ->options(AllergyVerificationStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
