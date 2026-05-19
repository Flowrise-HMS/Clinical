<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\VitalSign;

class PatientVitalsOverviewWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Current Vitals';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    protected function getStats(): array
    {
        if (! $this->patientId) {
            return [];
        }

        $vitals = VitalSign::query()
            ->where('patient_id', $this->patientId)
            ->when($this->encounterId, fn ($q) => $q->where('encounter_id', $this->encounterId))
            ->latest('recorded_at')
            ->first();

        if (! $vitals) {
            return [
                Stat::make('BP', '—'),
                Stat::make('HR', '—'),
                Stat::make('Temp', '—'),
                Stat::make('SpO₂', '—'),
            ];
        }

        return [
            Stat::make('BP', $vitals->blood_pressure ?? '—')
                ->description('mmHg')
                ->color($vitals->isAbnormalBloodPressure() ? 'warning' : 'success'),

            Stat::make('HR', $vitals->heart_rate ? "{$vitals->heart_rate} bpm" : '—')
                ->description('Heart Rate')
                ->color($vitals->heart_rate && $vitals->heart_rate > 100 ? 'warning' : 'success'),

            Stat::make('Temp', $vitals->temperature ? "{$vitals->temperature} °C" : '—')
                ->description('Temperature'),

            Stat::make('SpO₂', $vitals->spo2 ? "{$vitals->spo2} %" : '—')
                ->description('Oxygen Saturation')
                ->color($vitals->isLowOxygenSaturation() ? 'danger' : 'success'),
        ];
    }
}
