<?php

namespace Modules\Clinical\Classes\Fhir;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanIntervention;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\CarePlanRoutineCare;
use Modules\FHIR\Contracts\FhirResourceContract;

class FhirCarePlanTransformer implements FhirResourceContract
{
    private const CATEGORY_SYSTEM = 'http://flowrise.app/CodeSystem/care-plan-category';

    private const ACTIVITY_STATUS_MAP = [
        'planned' => 'not-started',
        'in_progress' => 'in-progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    public function resourceType(): string
    {
        return 'CarePlan';
    }

    public function toFhir(Model $model): array
    {
        $resource = [
            'resourceType' => 'CarePlan',
            'id' => $model->id,
            'status' => $model->status->value,
            'intent' => $model->intent->value,
            'category' => [[
                'coding' => [[
                    'system' => self::CATEGORY_SYSTEM,
                    'code' => $model->category->value,
                    'display' => $model->category->getLabel(),
                ]],
                'text' => $model->category->getLabel(),
            ]],
            'subject' => [
                'reference' => "Patient/{$model->patient_id}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$model->encounter_id}",
            ],
        ];

        if ($model->period_start) {
            $resource['period']['start'] = $model->period_start->toIso8601String();
        }

        if ($model->period_end) {
            $resource['period']['end'] = $model->period_end->toIso8601String();
        }

        if ($model->branch_id) {
            $resource['custodian'] = [
                'reference' => "Organization/{$model->branch_id}",
            ];
        }

        $addresses = $this->mapAddresses($model);
        if ($addresses !== []) {
            $resource['addresses'] = $addresses;
        }

        $goals = $this->mapGoals($model);
        if ($goals !== []) {
            $resource['goal'] = $goals;
        }

        $activities = $this->mapActivities($model);
        if ($activities !== []) {
            $resource['activity'] = $activities;
        }

        $notes = $this->mapNotes($model);
        if ($notes !== []) {
            $resource['note'] = $notes;
        }

        return $resource;
    }

    public function fromFhir(array $fhirResource): array
    {
        return [];
    }

    public function findById(string $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function query(): Builder
    {
        return CarePlan::with([
            'medicalDiagnoses',
            'routineCares',
            'diagnoses.objectives',
            'diagnoses.orders.interventions',
        ]);
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'patient' => ['column' => 'patient_id'],
            'encounter' => ['column' => 'encounter_id'],
            'status' => ['column' => 'status'],
            'category' => ['column' => 'category'],
            'date' => ['column' => 'period_start'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        return [];
    }

    /**
     * @return array<int, array{reference: string, display?: string}>
     */
    private function mapAddresses(CarePlan $carePlan): array
    {
        $addresses = [];

        foreach ($carePlan->medicalDiagnoses as $diagnosis) {
            $addresses[] = [
                'reference' => "Condition/{$diagnosis->id}",
                'display' => $diagnosis->description,
            ];
        }

        foreach ($carePlan->diagnoses as $diagnosis) {
            $addresses[] = [
                'reference' => "Condition/{$diagnosis->id}",
                'display' => $diagnosis->composed_statement,
            ];
        }

        return $addresses;
    }

    /**
     * @return array<int, array{reference: string, display: string}>
     */
    private function mapGoals(CarePlan $carePlan): array
    {
        return $carePlan->diagnoses
            ->flatMap(fn (CarePlanDiagnosis $diagnosis) => $diagnosis->objectives)
            ->map(fn ($objective) => [
                'reference' => "Goal/{$objective->id}",
                'display' => $objective->description,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{detail: array<string, mixed>, progress?: array<int, array{text: string, time?: string}>}>
     */
    private function mapActivities(CarePlan $carePlan): array
    {
        return $carePlan->diagnoses
            ->flatMap(fn (CarePlanDiagnosis $diagnosis) => $diagnosis->orders)
            ->map(fn (CarePlanOrder $order) => $this->mapOrderActivity($order))
            ->values()
            ->all();
    }

    /**
     * @return array{detail: array<string, mixed>, progress?: array<int, array{text: string, time?: string}>}
     */
    private function mapOrderActivity(CarePlanOrder $order): array
    {
        $activity = [
            'detail' => [
                'status' => self::ACTIVITY_STATUS_MAP[$order->status->value] ?? 'unknown',
                'description' => $order->instruction,
                'scheduledString' => $order->frequency,
            ],
        ];

        $progress = $order->interventions
            ->map(fn (CarePlanIntervention $intervention) => array_filter([
                'text' => $intervention->description,
                'time' => $intervention->performed_at?->toIso8601String(),
            ]))
            ->values()
            ->all();

        if ($progress !== []) {
            $activity['progress'] = $progress;
        }

        return $activity;
    }

    /**
     * @return array<int, array{text: string}>
     */
    private function mapNotes(CarePlan $carePlan): array
    {
        $notes = [];

        if ($carePlan->description) {
            $notes[] = ['text' => $carePlan->description];
        }

        foreach ($carePlan->routineCares as $routineCare) {
            $note = $this->routineCareNote($routineCare);
            if ($note !== null) {
                $notes[] = ['text' => $note];
            }
        }

        return $notes;
    }

    private function routineCareNote(CarePlanRoutineCare $routineCare): ?string
    {
        if ($routineCare->not_applicable) {
            return "{$routineCare->item->getLabel()}: not applicable";
        }

        $text = $routineCare->specification ?? $routineCare->notes;

        return $text ? "{$routineCare->item->getLabel()}: {$text}" : null;
    }
}
