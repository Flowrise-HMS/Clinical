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

    /**
     * Distinct Chart.js colors per vital so series are visually distinguishable.
     *
     * @var array<string, string>
     */
    protected const SERIES_COLORS = [
        'Temperature (°C)' => '#f59e0b',
        'Heart Rate (bpm)' => '#ef4444',
        'Respiratory Rate (/min)' => '#8b5cf6',
        'SpO₂ (%)' => '#06b6d4',
        'BP Systolic (mmHg)' => '#2563eb',
        'BP Diastolic (mmHg)' => '#93c5fd',
    ];

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
            $this->dataset('Temperature (°C)', $vitals->map(fn ($v) => $v->temperature ? (float) $v->temperature : null)->toArray()),
            $this->dataset('Heart Rate (bpm)', $vitals->map(fn ($v) => $v->heart_rate ? (int) $v->heart_rate : null)->toArray()),
            $this->dataset('Respiratory Rate (/min)', $vitals->map(fn ($v) => $v->respiratory_rate ? (int) $v->respiratory_rate : null)->toArray()),
            $this->dataset('SpO₂ (%)', $vitals->map(fn ($v) => $v->spo2 ? (int) $v->spo2 : null)->toArray()),
            $this->dataset('BP Systolic (mmHg)', $vitals->map(fn ($v) => $v->systolic_bp ? (int) $v->systolic_bp : null)->toArray()),
            $this->dataset('BP Diastolic (mmHg)', $vitals->map(fn ($v) => $v->diastolic_bp ? (int) $v->diastolic_bp : null)->toArray()),
        ];

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }

    /**
     * @param  array<int, float|int|null>  $data
     * @return array<string, mixed>
     */
    protected function dataset(string $label, array $data): array
    {
        $color = self::SERIES_COLORS[$label];

        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'pointBackgroundColor' => $color,
            'pointBorderColor' => $color,
            'yAxisID' => 'y',
            'tension' => 0.2,
            'fill' => false,
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
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
