<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class ServiceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('request_number')
                            ->label('Request Number'),
                        TextEntry::make('status')
                            ->label('Status'),
                        TextEntry::make('priority')
                            ->label('Priority'),
                    ]),

                Section::make('Patient Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('patient.full_name')
                            ->label('Patient')
                            ->placeholder('Guest Request'),
                        TextEntry::make('guest_name')
                            ->label('Guest Name')
                            ->placeholder('-'),
                        TextEntry::make('guest_phone')
                            ->label('Guest Phone')
                            ->placeholder('-'),
                    ]),

                Section::make('Service Items')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('service.name')
                                    ->label('Service'),
                                TextEntry::make('service.code')
                                    ->label('Code'),
                                TextEntry::make('quantity')
                                    ->label('Qty'),
                                CurrencyEntry::make('unit_price')
                                    ->label('Unit Price'),
                                TextEntry::make('status')
                                    ->label('Status'),
                            ])
                            ->columns(5),
                    ]),

                Section::make('Summary')
                    ->columns(2)
                    ->schema([
                        CurrencyEntry::make('total_amount')
                            ->label('Total Amount'),
                        TextEntry::make('progress_percentage')
                            ->label('Progress')
                            ->suffix('%'),
                    ]),

                Section::make('Ordering')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('orderedBy.name')
                            ->label('Ordered By'),
                        TextEntry::make('encounter.encounter_number')
                            ->label('Encounter')
                            ->placeholder('-'),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ]),
            ]);
    }
}
