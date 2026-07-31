<?php

use Livewire\Livewire;
use Modules\Clinical\Filament\Widgets\PatientVitalsChartWidget;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class);

it('assigns a distinct color to each vitals chart series', function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical']);

    $branch = Branch::factory()->default()->create();
    $patient = Patient::withoutEvents(
        fn (): Patient => Patient::factory()->create(['branch_id' => $branch->id])
    );

    VitalSign::factory()->create([
        'patient_id' => $patient->id,
        'branch_id' => $branch->id,
        'recorded_at' => now(),
    ]);

    $component = Livewire::test(PatientVitalsChartWidget::class, [
        'patientId' => $patient->id,
    ]);

    $data = invade($component->instance())->getData();
    $datasets = $data['datasets'];
    $colors = collect($datasets)->pluck('borderColor');

    expect($datasets)->toHaveCount(6)
        ->and($colors->unique()->count())->toBe(6)
        ->and($datasets[0])->toMatchArray([
            'label' => 'Temperature (°C)',
            'borderColor' => '#f59e0b',
            'backgroundColor' => '#f59e0b',
        ])
        ->and($datasets[1])->toMatchArray([
            'label' => 'Heart Rate (bpm)',
            'borderColor' => '#ef4444',
        ])
        ->and($datasets[2])->toMatchArray([
            'label' => 'Respiratory Rate (/min)',
            'borderColor' => '#8b5cf6',
        ])
        ->and($datasets[3])->toMatchArray([
            'label' => 'SpO₂ (%)',
            'borderColor' => '#06b6d4',
        ])
        ->and($datasets[4])->toMatchArray([
            'label' => 'BP Systolic (mmHg)',
            'borderColor' => '#2563eb',
        ])
        ->and($datasets[5])->toMatchArray([
            'label' => 'BP Diastolic (mmHg)',
            'borderColor' => '#93c5fd',
        ]);
});
