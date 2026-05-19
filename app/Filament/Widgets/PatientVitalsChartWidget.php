<?php

namespace Modules\Clinical\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Clinical\Models\VitalSign;

class PatientVitalsChartWidget extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Vitals Trend';

    #[Reactive]
    public ?string $patientId = null;

    #[Reactive]
    public ?string $encounterId = null;

    protected int|string|array $columnSpan = 'full';
    protected bool $isCollapsible = true;

    protected int $limit = 30;

    protected function getData(): array
    {
        if (! $this->patientId) {
            return $this->emptyData();
        }

        $vitals = VitalSign::query()
            ->where('patient_id', $this->patientId)
            ->when($this->encounterId, fn ($q) => $q->where('encounter_id', $this->encounterId))
            ->whereNotNull('recorded_at')
            ->orderBy('recorded_at')
            ->limit($this->limit)
            ->get();

        if ($vitals->isEmpty()) {
            return $this->emptyData();
        }

        $labels = $vitals->map(fn ($v) => $v->recorded_at?->format('Y-m-d H:i'))->toArray();

        $datasets = [
            [
                'label' => 'Temperature (°C)',
                'data' => $vitals->map(fn ($v) => $v->temperature ? (float) $v->temperature : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
            [
                'label' => 'Heart Rate (bpm)',
                'data' => $vitals->map(fn ($v) => $v->heart_rate ? (int) $v->heart_rate : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
            [
                'label' => 'Respiratory Rate (/min)',
                'data' => $vitals->map(fn ($v) => $v->respiratory_rate ? (int) $v->respiratory_rate : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
            [
                'label' => 'SpO₂ (%)',
                'data' => $vitals->map(fn ($v) => $v->spo2 ? (int) $v->spo2 : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
            [
                'label' => 'BP Systolic (mmHg)',
                'data' => $vitals->map(fn ($v) => $v->systolic_bp ? (int) $v->systolic_bp : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
            [
                'label' => 'BP Diastolic (mmHg)',
                'data' => $vitals->map(fn ($v) => $v->diastolic_bp ? (int) $v->diastolic_bp : null)->toArray(),
                'yAxisID' => 'y',
                'tension' => 0.2,
                'fill' => false,
            ],
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => ['display' => true, 'text' => 'Recorded at'],
                ],
                'y' => [
                    'display' => true,
                    'title' => ['display' => true, 'text' => 'Value'],
                ],
            ],
        ];
    }

    protected function emptyData(): array
    {
        return [
            'labels' => [],
            'datasets' => [],
        ];
    }
}
