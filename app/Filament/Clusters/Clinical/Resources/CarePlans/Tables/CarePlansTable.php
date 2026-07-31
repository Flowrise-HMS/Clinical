<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanStatus;

class CarePlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('patient.full_name')
                    ->label('Patient')
                    ->searchable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('title')
                    ->placeholder('Untitled care plan')
                    ->searchable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),
                TextColumn::make('activated_at')
                    ->label('Activated')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not activated'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(CarePlanCategory::class),
                SelectFilter::make('status')
                    ->options(CarePlanStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
