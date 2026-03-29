<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\RequestPriority;

class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Patient Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('patient_id')
                                    ->relationship('patient', 'full_name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Patient'),

                                TextInput::make('guest_name')
                                    ->label('Guest Name')
                                    ->helperText('For walk-in guests without patient record'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('guest_phone')
                                    ->label('Guest Phone')
                                    ->tel(),

                                TextInput::make('guest_email')
                                    ->label('Guest Email')
                                    ->email()
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Request Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('priority')
                                    ->enum(RequestPriority::class)
                                    ->options(RequestPriority::class)
                                    ->default('routine')
                                    ->required()
                                    ->label('Priority'),

                                Select::make('encounter_id')
                                    ->relationship('encounter', 'encounter_number')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Encounter'),
                            ]),

                        Textarea::make('notes')
                            ->label('Notes/Instructions')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Service Items')
                    ->headerActions([
                        Action::make('add_service')
                            ->label('Add Service')
                            ->icon('heroicon-m-plus')
                            ->form([
                                Select::make('service_id')
                                    ->relationship('items.service', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->label('Service'),
                            ])
                            ->action(function (array $data) {
                                // Add service item
                            }),
                    ])
                    ->schema([
                        TextEntry::make('items_count')
                            ->label('Service Items')
                            ->state(fn ($record) => $record ? $record?->items?->count().' items' : 'No items'),
                    ]),
            ]);
    }
}
