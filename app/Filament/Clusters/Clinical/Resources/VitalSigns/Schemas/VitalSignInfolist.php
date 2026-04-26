<?php

namespace Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VitalSignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('recorded_at')
                    ->state(fn($record) => $record->recorded_at?->diffForHumans() ?? 'Unknown')
                    ->placeholder('-'),
                TextEntry::make('blood_pressure')
                    ->suffix('mmHg')
                    ->placeholder('-'),
                TextEntry::make('heart_rate')
                    ->suffix('bpm')
                    ->placeholder('-'),
                TextEntry::make('temperature')
                    ->suffix('°C')
                    ->placeholder('-'),
                TextEntry::make('spo2')
                    ->suffix('%')
                    ->color(fn($record) => $record->isLowOxygenSaturation() ? 'warning' : 'ray')
                    ->placeholder('-'),
                TextEntry::make('respiratory_rate')
                    ->suffix('/min')
                    ->placeholder('-'),
                TextEntry::make('bmi')
                    ->suffix(fn($record) => $record?->bmi_category)
                    ->placeholder('-'),

            ]);
    }
}
