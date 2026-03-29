<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EncounterInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Encounter Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('encounter_number')
                            ->label('Encounter Number'),
                        TextEntry::make('type')
                            ->label('Type'),
                        TextEntry::make('status')
                            ->label('Status'),
                    ]),

                Section::make('Patient Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('patient.full_name')
                            ->label('Patient')
                            ->placeholder('Guest Encounter'),
                        TextEntry::make('guest_name')
                            ->label('Guest Name')
                            ->placeholder('-'),
                        TextEntry::make('guest_phone')
                            ->label('Guest Phone')
                            ->placeholder('-'),
                    ]),

                Section::make('Clinical Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('priority')
                            ->label('Priority'),
                        TextEntry::make('chief_complaint')
                            ->label('Chief Complaint')
                            ->placeholder('-'),
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->placeholder('-'),
                        TextEntry::make('location.name')
                            ->label('Location')
                            ->placeholder('-'),
                    ]),

                Section::make('Admission Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('admitted_at')
                            ->label('Admitted At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('discharged_at')
                            ->label('Discharged At')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('duration')
                            ->label('Duration')
                            ->placeholder('-'),
                        TextEntry::make('discharge_disposition')
                            ->label('Disposition')
                            ->placeholder('-'),
                    ]),

                Section::make('Participants')
                    ->schema([
                        TextEntry::make('participants_count')
                            ->label('Active Participants')
                            ->getStateUsing(fn ($record) => $record->activeParticipants->count()),
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
