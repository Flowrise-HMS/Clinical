<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceVariant;

class RequestItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(4)
                    ->schema([
                        Select::make('service_id')
                            ->label('Service')
                            ->options(fn () => Service::active()->billable()->with('category')->get()->groupBy('category.name')->map(fn ($group) => $group->pluck('name', 'id'))->toArray())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($set) => $set('service_variant_id', null)),

                        Select::make('service_variant_id')
                            ->label('Variant')
                            ->options(function (Get $get) {
                                $serviceId = $get('service_id');
                                if (! $serviceId) {
                                    return [];
                                }
                                $service = Service::find($serviceId);
                                if (! $service) {
                                    return [];
                                }

                                return $service->variants()->active()->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state) {
                                    $variant = ServiceVariant::find($state);
                                    if ($variant) {
                                        $set('unit_price', $variant->price);
                                    }
                                } else {
                                    $serviceId = $get('service_id');
                                    if ($serviceId) {
                                        $service = Service::find($serviceId);
                                        if ($service) {
                                            $set('unit_price', $service->getDefaultPrice());
                                        }
                                    }
                                }
                            }),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),

                        TextInput::make('unit_price')
                            ->label('Unit Price')
                            ->numeric()
                            ->prefix(config('core.default_currency_symbol'))
                            ->required(),
                    ]),

                Grid::make(3)
                    ->schema([
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->state(function (Get $get) {
                                $quantity = $get('quantity') ?? 1;
                                $unitPrice = $get('unit_price') ?? 0;

                                return number_format($quantity * $unitPrice, 2);
                            })->prefix(config('core.default_currency_symbol')),

                        TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix(config('core.default_currency_symbol'))
                            ->default(0),

                        TextEntry::make('total')
                            ->label('Total')
                            ->state(function (Get $get) {
                                $quantity = $get('quantity') ?? 1;
                                $unitPrice = $get('unit_price') ?? 0;
                                $discount = $get('discount_amount') ?? 0;

                                return number_format(($quantity * $unitPrice) - $discount, 2);
                            })->prefix(config('core.default_currency_symbol')),
                    ]),

                Select::make('status')
                    ->options(RequestItemStatus::class)
                    ->default(RequestItemStatus::PENDING)
                    ->required()
                    ->label('Status'),

                Textarea::make('notes')
                    ->label('Notes/Instructions')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service.name')
            ->columns([
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
            ])
            ->footerActions([
                // Footer with totals
            ]);
    }
}
