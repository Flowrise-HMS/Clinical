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
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceVariant;

class RequestItemForm
{
    public static function getItemFields(): array
    {
        return [
            Select::make('service_id')
                ->label('Service')
                ->options(fn () => Service::active()->billable()->nonMedication()->with('category')->get()->groupBy('category.name')->map(fn ($group) => $group->pluck('name', 'id'))->toArray())
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $set('service_variant_id', null);
                    if ($state) {
                        $service = Service::find($state);
                        $set('unit_price', $service?->price ?? 0);
                    }
                }),

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
                ->live()
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
