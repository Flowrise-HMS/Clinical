<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Tasks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Task Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status'),
                        TextEntry::make('outcome')
                            ->label('Outcome')
                            ->placeholder('-'),
                    ]),

                Section::make('Service')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('requestItem.service.name')
                            ->label('Service'),
                        TextEntry::make('requestItem.service.code')
                            ->label('Code'),
                        TextEntry::make('requestItem.quantity')
                            ->label('Quantity'),
                    ]),

                Section::make('Request')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('requestItem.serviceRequest.request_number')
                            ->label('Request Number'),
                        TextEntry::make('requestItem.serviceRequest.patient.full_name')
                            ->label('Patient')
                            ->placeholder('Guest'),
                    ]),

                Section::make('Performance')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('performedBy.name')
                            ->label('Performed By')
                            ->placeholder('-'),
                        TextEntry::make('started_at')
                            ->label('Started At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label('Completed At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('duration_display')
                            ->label('Duration')
                            ->placeholder('-'),
                    ]),

                Section::make('Results')
                    ->schema([
                        TextEntry::make('results')
                            ->label('')
                            ->placeholder('No results recorded'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('')
                            ->placeholder('No notes'),
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
