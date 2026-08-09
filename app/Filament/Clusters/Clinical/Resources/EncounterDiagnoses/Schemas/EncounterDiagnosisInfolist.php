<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\EncounterDiagnoses\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\EncounterDiagnosis;

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
                        TextEntry::make('catalogue_entry')
                            ->label('Catalogue entry')
                            ->placeholder('Free-text diagnosis')
                            ->state(fn (EncounterDiagnosis $record): ?string => self::diagnosisCode($record)?->description)
                            ->helperText(fn (EncounterDiagnosis $record): ?string => self::diagnosisCode($record)?->code),
                        TextEntry::make('catalogue_source')
                            ->label('Code source')
                            ->badge()
                            ->placeholder('—')
                            ->state(fn (EncounterDiagnosis $record): ?string => self::diagnosisCode($record)?->source),
                        TextEntry::make('icd_entity_id')
                            ->label('ICD entity ID')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('icd_uri')
                            ->label('ICD reference')
                            ->placeholder('—')
                            ->url(fn (EncounterDiagnosis $record): ?string => $record->icd_uri)
                            ->openUrlInNewTab()
                            ->columnSpanFull(),
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
                        TextEntry::make('recorded_by')
                            ->label('Recorded by')
                            ->placeholder('—')
                            ->state(fn (EncounterDiagnosis $record): ?string => $record->loadMissing('orderedBy')->orderedBy?->name),
                        TextEntry::make('created_at')
                            ->label('Recorded')
                            ->dateTime(),
                        TextEntry::make('encounter_number')
                            ->label('Encounter')
                            ->placeholder('—')
                            ->state(fn (EncounterDiagnosis $record): ?string => $record->loadMissing('encounter')->encounter?->encounter_number),
                        IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                    ]),
            ]);
    }

    /**
     * Resolved defensively because the infolist runs in widgets and tables that eager-load different
     * relationship sets while lazy loading is disabled.
     */
    protected static function diagnosisCode(EncounterDiagnosis $record): ?DiagnosisCode
    {
        return $record->loadMissing('diagnosisCode')->diagnosisCode;
    }
}
