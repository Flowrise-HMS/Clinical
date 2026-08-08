<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EncounterDiagnosisInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Diagnosis')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('certainty')
                            ->label('Certainty')
                            ->badge(),
                        IconEntry::make('is_new_case')
                            ->label('New case')
                            ->boolean(),
                    ]),

                Section::make('Classification')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('description')
                            ->label('Diagnosis')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('icd_code')
                            ->label('ICD code')
                            ->placeholder('—'),
                        TextEntry::make('icd10_code')
                            ->label('ICD-10 code')
                            ->placeholder('—'),
                    ]),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->hiddenLabel()
                            ->placeholder('No notes recorded'),
                    ]),

                Section::make('Record')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('orderedBy.name')
                            ->label('Recorded by')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Recorded')
                            ->dateTime(),
                    ]),
            ]);
    }
}
