<?php

namespace Modules\Clinical\Classes\Fhir;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Models\CarePlanEvaluation;
use Modules\Clinical\Models\CarePlanObjective;
use Modules\FHIR\Contracts\FhirResourceContract;

class FhirGoalTransformer implements FhirResourceContract
{
    private const ACHIEVEMENT_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/goal-achievement';

    public function resourceType(): string
    {
        return 'Goal';
    }

    public function toFhir(Model $model): array
    {
        $carePlan = $model->diagnosis->carePlan;
        $diagnosis = $model->diagnosis;

        $resource = [
            'resourceType' => 'Goal',
            'id' => $model->id,
            'lifecycleStatus' => $model->lifecycle_status->value,
            'achievementStatus' => [
                'coding' => [[
                    'system' => self::ACHIEVEMENT_STATUS_SYSTEM,
                    'code' => $model->achievement_status->value,
                    'display' => $model->achievement_status->getLabel(),
                ]],
                'text' => $model->achievement_status->getLabel(),
            ],
            'description' => [
                'text' => $model->description,
            ],
            'subject' => [
                'reference' => "Patient/{$carePlan->patient_id}",
            ],
            'addresses' => [[
                'reference' => "Condition/{$diagnosis->id}",
                'display' => $diagnosis->composed_statement,
            ]],
        ];

        if ($model->start_date) {
            $resource['startDate'] = $model->start_date->toDateString();
        }

        $target = array_filter([
            'measure' => $model->target_measure ? ['text' => $model->target_measure] : null,
            'detailString' => $model->target_value,
            'dueDate' => $model->target_date?->toDateString(),
        ]);
        if ($target !== []) {
            $resource['target'] = [$target];
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
        return CarePlanObjective::with([
            'diagnosis.carePlan',
            'evaluations',
        ]);
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'patient' => ['relation' => 'diagnosis.carePlan', 'column' => 'patient_id'],
            'encounter' => ['relation' => 'diagnosis.carePlan', 'column' => 'encounter_id'],
            'lifecycle-status' => ['column' => 'lifecycle_status'],
            'achievement-status' => ['column' => 'achievement_status'],
            'date' => ['column' => 'start_date'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        return [];
    }

    /**
     * @return array<int, array{text: string, time?: string}>
     */
    private function mapNotes(CarePlanObjective $objective): array
    {
        return $objective->evaluations
            ->map(fn (CarePlanEvaluation $evaluation) => array_filter([
                'text' => $evaluation->findings,
                'time' => $evaluation->evaluated_at?->toIso8601String(),
            ]))
            ->values()
            ->all();
    }
}
