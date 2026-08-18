<?php

namespace Modules\Clinical\Filament\Clusters\Workspace\Schemas\CarePlan;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\CarePlan;

class RoutineCareForm
{
    /**
     * @return array<int, Component>
     */
    public static function components(?CarePlan $carePlan = null): array
    {
        $existing = $carePlan
            ? $carePlan->routineCares()->get()->keyBy(fn ($row) => $row->item->value)
            : collect();

        $defaults = collect(RoutineCareItem::cases())
            ->reject(fn (RoutineCareItem $item): bool => $item === RoutineCareItem::OTHER)
            ->map(function (RoutineCareItem $item) use ($existing): array {
                $row = $existing->get($item->value);

                return [
                    'item' => $item->value,
                    'item_label' => $item->getLabel(),
                    'specification' => $row?->specification,
                    'not_applicable' => (bool) ($row?->not_applicable ?? false),
                    'notes' => $row?->notes,
                ];
            })
            ->values()
            ->all();

        return [
            Repeater::make('items')
                ->label('Routine care checklist')
                ->helperText('Review each item once. Mark N/A when not relevant, or enter instructions. Placeholders are suggestions only.')
                ->schema([
                    Hidden::make('item')->required(),
                    TextInput::make('item_label')
                        ->label('Item')
                        ->disabled()
                        ->dehydrated(false),
                    Toggle::make('not_applicable')
                        ->live()
                        ->inline(false)
                        ->label('Not applicable'),
                    Textarea::make('specification')
                        ->rows(2)
                        ->nullable()
                        ->placeholder(fn (Get $get): string => enum_try_from(RoutineCareItem::class, $get('item'))?->getDescription()
                            ?? 'Enter care instructions for this item')
                        ->helperText('Describe how this care should be given for this patient.')
                        ->required(fn (Get $get): bool => ! $get('not_applicable'))
                        ->disabled(fn (Get $get): bool => (bool) $get('not_applicable'))
                        ->dehydrated()
                        ->label('Care instruction'),
                    Textarea::make('notes')
                        ->rows(1)
                        ->nullable()
                        ->placeholder('Optional notes')
                        ->label('Notes'),
                ])
                ->default($defaults)
                ->deletable(false)
                ->addable(false)
                ->reorderable(false)
                ->itemLabel(fn (array $state): ?string => $state['item_label']
                    ?? enum_try_from(RoutineCareItem::class, $state['item'] ?? null)?->getLabel())
                ->collapsed(false),
        ];
    }
}
