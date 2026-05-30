<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\MedicationAdministrations\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MedicationAdministrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Administration Status')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status'),
                        TextEntry::make('quantity_given')
                            ->label('Quantity Given'),
                        TextEntry::make('doseUnit.label')
                            ->label('Dose Unit')
                            ->placeholder('-'),
                    ]),

                Section::make('Medication Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('requestItem.service.name')
                            ->label('Medication / Drug'),
                        TextEntry::make('requestItem.service.code')
                            ->label('Code')
                            ->placeholder('-'),
                    ]),

                Section::make('Administration Log')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('administeredBy.name')
                            ->label('Administered By')
                            ->placeholder('-'),
                        TextEntry::make('started_at')
                            ->label('Started At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('ended_at')
                            ->label('Ended At')
                            ->dateTime()
                            ->placeholder('-'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('No notes recorded'),
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
