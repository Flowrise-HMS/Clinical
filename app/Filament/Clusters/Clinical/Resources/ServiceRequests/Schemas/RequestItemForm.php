<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Core\Classes\Support\BillableServiceCatalog;

class RequestItemForm
{
    public static function getItemFields(): array
    {
        return [
            Select::make('service_id')
                ->label('Service')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => BillableServiceCatalog::search($search))
                ->getOptionLabelUsing(fn ($value): ?string => BillableServiceCatalog::labelForId($value))
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set): void {
                    $set('service_variant_id', null);
                    $set('unit_price', BillableServiceCatalog::defaultPrice($state));
                }),

            Select::make('service_variant_id')
                ->label('Variant')
                ->options(fn (Get $get): array => BillableServiceCatalog::variantsForService($get('service_id')))
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                    $variantPrice = BillableServiceCatalog::variantPrice($state);

                    if ($variantPrice !== null) {
                        $set('unit_price', $variantPrice);

                        return;
                    }

                    $set('unit_price', BillableServiceCatalog::defaultPrice($get('service_id')));
                }),

            TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->required()
                ->live(),

            TextInput::make('unit_price')
                ->label('Unit Price')
                ->numeric()
                ->prefix(config('core.default_currency_symbol'))
                ->required()
                ->live(),
        ];
    }

    public static function getFormSchema(): array
    {
        return [
            Grid::make(4)
                ->schema(self::getItemFields()),

            Grid::make(3)
                ->schema([
                    TextEntry::make('subtotal')
                        ->label('Subtotal')
                        ->state(function (Get $get) {
                            $quantity = $get('quantity') ?? 1;
                            $unitPrice = $get('unit_price') ?? 0;

                            return config('core.default_currency_symbol').' '.number_format($quantity * $unitPrice, 2);
                        }),

                    TextInput::make('discount_amount')
                        ->label('Discount')
                        ->numeric()
                        ->prefix(config('core.default_currency_symbol'))
                        ->default(0)
                        ->live(),

                    TextEntry::make('total')
                        ->label('Total')
                        ->state(function (Get $get) {
                            $quantity = $get('quantity') ?? 1;
                            $unitPrice = $get('unit_price') ?? 0;
                            $discount = $get('discount_amount') ?? 0;

                            return config('core.default_currency_symbol').' '.number_format(($quantity * $unitPrice) - $discount, 2);
                        }),
                ]),

            Select::make('status')
                ->options(RequestItemStatus::class)
                ->default(RequestItemStatus::PENDING)
                ->required()
                ->label('Status'),

            Textarea::make('notes')
                ->label('Notes/Instructions')
                ->rows(2),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::getFormSchema());
    }
}
