<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;

class ServiceRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(array_merge([
                Section::make('Patient Information')
                    ->description('Select an existing patient or register a walk-in guest')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('patient_id')
                                    ->relationship('patient', 'mrn')
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record ? $record->full_name : 'Select patient')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->label('Patient (Existing)'),

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

            ], self::quickElements()));
    }

    public static function quickElements(): array
    {
        return [
            Section::make('Request Details')
                ->description('Service request information')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('encounter_id')
                                ->relationship('encounter', 'encounter_number')
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record ? "{$record->encounter_number} - {$record->display_name}" : 'Select encounter')
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->label('Linked Encounter'),

                            Select::make('priority')
                                ->options(RequestPriority::class)
                                ->default('routine')
                                ->required()
                                ->label('Priority'),

                            Select::make('status')
                                ->options(RequestStatus::class)
                                ->default('draft')
                                ->required()
                                ->label('Status'),
                        ]),

                    Fieldset::make('Ordering Provider')
                        ->schema([
                            TextEntry::make('ordered_by_name')
                                ->label('Ordered By')
                                ->state(fn ($record) => $record?->orderedBy?->name ?? 'Not assigned'),
                        ]),

                    RichEditor::make('notes')
                        ->label('Instructions/Notes')
                        ->toolbarButtons([
                            'attachFiles',
                            'bold',
                            'bulletList',
                            'italic',
                            'orderedList',
                            'strike',
                        ])
                        ->fileAttachmentsDisk('local')
                        ->fileAttachmentsDirectory('service-requests')
                        ->columnSpanFull(),
                ]),

            Section::make('Service Items')
                ->description('Add services to be requested. Use the "Add Item" button below.')
                ->collapsible()
                ->collapsed(fn ($record) => $record && $record->items->isEmpty())
                ->schema([
                    TextEntry::make('items_summary')
                        ->label('Current Items')
                        ->state(function ($record) {
                            if (! $record) {
                                return 'No items added yet';
                            }
                            $count = $record->items->count();
                            $total = $record->total_amount;

                            return "{$count} item(s) - Total: $".number_format($total, 2);
                        }),
                ]),
        ];
    }
}
