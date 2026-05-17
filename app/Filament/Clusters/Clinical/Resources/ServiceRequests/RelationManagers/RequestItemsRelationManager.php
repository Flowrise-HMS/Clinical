<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas\RequestItemForm;

class RequestItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return RequestItemForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service.name')
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('service_variant.name')
                    ->label('Variant')
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('service.code')
                    ->label('Code')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable(),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money()
                    ->sortable(),

                TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_price')
                    ->label('Total')
                    ->money()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('fulfilledBy.name')
                    ->label('Fulfilled By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('fulfilled_at')
                    ->label('Fulfilled At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(RequestItemStatus::class)
                    ->label('Status'),

                SelectFilter::make('service')
                    ->relationship('service', 'name')
                    ->label('Service')
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Item'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
