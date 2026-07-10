<?php

namespace Modules\Clinical\Classes\Fhir;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\FHIR\Contracts\FhirResourceContract;

class FhirConditionTransformer implements FhirResourceContract
{
    private const ICD_SYSTEM = 'http://hl7.org/fhir/sid/icd-10-CM';

    private const CLINICAL_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-clinical';

    private const CATEGORY_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-category';

    private const VERIFICATION_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/condition-ver-status';

    public function resourceType(): string
    {
        return 'Condition';
    }

    public function toFhir(Model $model): array
    {
        $resource = [
            'resourceType' => 'Condition',
            'id' => $model->id,
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => self::CLINICAL_STATUS_SYSTEM,
                        'code' => $model->is_active ? 'active' : 'inactive',
                    ],
                ],
            ],
            'verificationStatus' => [
                'coding' => [
                    [
                        'system' => self::VERIFICATION_STATUS_SYSTEM,
                        'code' => 'confirmed',
                    ],
                ],
            ],
            'category' => [
                [
                    'coding' => [
                        [
                            'system' => self::CATEGORY_SYSTEM,
                            'code' => 'encounter-diagnosis',
                            'display' => 'Encounter Diagnosis',
                        ],
                    ],
                ],
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => self::ICD_SYSTEM,
                        'code' => $model->icd_code ?? 'unknown',
                        'display' => $model->description ?? 'Unknown condition',
                    ],
                ],
                'text' => $model->description,
            ],
            'subject' => [
                'reference' => "Patient/{$model->patient_id}",
            ],
            'encounter' => [
                'reference' => "Encounter/{$model->encounter_id}",
            ],
        ];

        if ($model->created_at) {
            $resource['recordedDate'] = $model->created_at instanceof DateTime
                ? $model->created_at->toIso8601String()
                : Carbon::parse($model->created_at)->toIso8601String();
        }

        if ($model->notes) {
            $resource['note'] = [
                ['text' => $model->notes],
            ];
        }

        return $resource;
    }

    public function fromFhir(array $fhirResource): array
    {
        $attrs = [];

        if (isset($fhirResource['clinicalStatus']['coding'][0]['code'])) {
            $attrs['is_active'] = $fhirResource['clinicalStatus']['coding'][0]['code'] === 'active';
        }

        if (isset($fhirResource['code']['coding'][0]['code'])) {
            $attrs['icd_code'] = $fhirResource['code']['coding'][0]['code'];
            $attrs['description'] = $fhirResource['code']['coding'][0]['display']
                ?? $fhirResource['code']['text']
                ?? null;
        } elseif (isset($fhirResource['code']['text'])) {
            $attrs['description'] = $fhirResource['code']['text'];
        }

        if (isset($fhirResource['subject']['reference'])) {
            $parts = explode('/', $fhirResource['subject']['reference']);
            $attrs['patient_id'] = end($parts);
        }

        if (isset($fhirResource['encounter']['reference'])) {
            $parts = explode('/', $fhirResource['encounter']['reference']);
            $attrs['encounter_id'] = end($parts);
        }

        if (isset($fhirResource['note'][0]['text'])) {
            $attrs['notes'] = $fhirResource['note'][0]['text'];
        }

        if (isset($fhirResource['recordedDate'])) {
            $attrs['created_at'] = $fhirResource['recordedDate'];
        }

        return $attrs;
    }

    public function findById(string $id): ?Model
    {
        return EncounterDiagnosis::with([
            'encounter',
            'patient',
            'diagnosisCode',
        ])->find($id);
    }

    public function query(): Builder
    {
        return EncounterDiagnosis::with([
            'encounter',
            'patient',
            'diagnosisCode',
        ]);
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'subject' => ['column' => 'patient_id'],
            'encounter' => ['column' => 'encounter_id'],
            'code' => ['column' => 'icd_code'],
            'clinical-status' => ['column' => 'is_active'],
            'category' => ['column' => 'type'],
            'recorded-date' => ['column' => 'created_at'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        $errors = [];

        if (! isset($fhirResource['subject']['reference'])) {
            $errors['con-1'] = 'Condition SHALL have a subject.';
        }

        if (! isset($fhirResource['code']['coding'][0]['code']) && ! isset($fhirResource['code']['text'])) {
            $errors['con-2'] = 'Condition SHALL have a code.';
        }

        if (! isset($fhirResource['clinicalStatus']['coding'][0]['code'])) {
            $errors['con-3'] = 'Condition SHALL have a clinicalStatus.';
        }

        return $errors;
    }
}
