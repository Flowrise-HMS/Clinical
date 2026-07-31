<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\CarePlans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CarePlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Care Plan')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('patient.full_name')
                            ->label('Patient'),
                        TextEntry::make('category')
                            ->badge(),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('title')
                            ->placeholder('Untitled care plan'),
                        TextEntry::make('author.name')
                            ->label('Author'),
                        TextEntry::make('activated_at')
                            ->label('Activated')
                            ->dateTime()
                            ->placeholder('Not activated'),
                    ]),
                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->placeholder('No description recorded'),
                    ]),
            ]);
    }
}
