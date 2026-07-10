<?php

namespace Modules\Clinical\Classes\Fhir;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Models\Allergy;
use Modules\FHIR\Contracts\FhirResourceContract;

class FhirAllergyIntoleranceTransformer implements FhirResourceContract
{
    private const CATEGORY_MAP = [
        AllergenType::MEDICATION->value => 'medication',
        AllergenType::FOOD->value => 'food',
        AllergenType::ENVIRONMENTAL->value => 'environment',
        AllergenType::BIOLOGICAL->value => 'biologic',
        AllergenType::OTHER->value => 'environment',
    ];

    private const SEVERITY_CRITICALITY_MAP = [
        AllergySeverity::MILD->value => 'low',
        AllergySeverity::MODERATE->value => 'low',
        AllergySeverity::SEVERE->value => 'high',
        AllergySeverity::LIFE_THREATENING->value => 'high',
    ];

    private const SEVERITY_MAP = [
        AllergySeverity::MILD->value => 'mild',
        AllergySeverity::MODERATE->value => 'moderate',
        AllergySeverity::SEVERE->value => 'severe',
        AllergySeverity::LIFE_THREATENING->value => 'severe',
    ];

    private const VERIFICATION_STATUS_MAP = [
        AllergyVerificationStatus::UNVERIFIED->value => 'unconfirmed',
        AllergyVerificationStatus::VERIFIED->value => 'confirmed',
        AllergyVerificationStatus::REFUTED->value => 'refuted',
    ];

    private const CLINICAL_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical';

    private const VERIFICATION_STATUS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification';

    private const CATEGORY_SYSTEM = 'http://terminology.hl7.org/CodeSystem/allergy-intolerance-category';

    public function resourceType(): string
    {
        return 'AllergyIntolerance';
    }

    public function toFhir(Model $model): array
    {
        $resource = [
            'resourceType' => 'AllergyIntolerance',
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
                        'code' => isset($model->verification_status)
                            ? (self::VERIFICATION_STATUS_MAP[$model->verification_status->value] ?? 'unconfirmed')
                            : 'unconfirmed',
                    ],
                ],
            ],
            'category' => [
                self::CATEGORY_MAP[$model->allergen_type?->value] ?? 'environment',
            ],
            'code' => [
                'coding' => [
                    [
                        'code' => $model->allergen_code ?? 'unknown',
                        'display' => $model->allergen,
                    ],
                ],
                'text' => $model->allergen,
            ],
            'patient' => [
                'reference' => "Patient/{$model->patient_id}",
            ],
            'recordedDate' => $model->created_at
                ? ($model->created_at instanceof DateTime
                    ? $model->created_at->toIso8601String()
                    : Carbon::parse($model->created_at)->toIso8601String())
                : null,
        ];

        if ($model->severity) {
            $resource['criticality'] = self::SEVERITY_CRITICALITY_MAP[$model->severity->value] ?? 'unable-to-assess';
        }

        if ($model->onset_date) {
            $resource['onsetDateTime'] = $model->onset_date instanceof DateTime
                ? $model->onset_date->toIso8601String()
                : Carbon::parse($model->onset_date)->toIso8601String();
        }

        if ($model->verified_at) {
            $resource['lastOccurrence'] = $model->verified_at instanceof DateTime
                ? $model->verified_at->toIso8601String()
                : Carbon::parse($model->verified_at)->toIso8601String();
        }

        $reaction = $this->buildReaction($model);
        if ($reaction) {
            $resource['reaction'] = [$reaction];
        }

        if ($model->notes) {
            $resource['note'] = [
                ['text' => $model->notes],
            ];
        }

        return $resource;
    }

    private function buildReaction(Allergy $model): ?array
    {
        if (! $model->reaction && ! $model->severity) {
            return null;
        }

        $reaction = [];

        if ($model->reaction) {
            $reaction['manifestation'] = [
                ['text' => $model->reaction],
            ];
        }

        if ($model->severity) {
            $reaction['severity'] = self::SEVERITY_MAP[$model->severity->value] ?? 'mild';
        }

        return $reaction;
    }

    public function fromFhir(array $fhirResource): array
    {
        $attrs = [];

        if (isset($fhirResource['clinicalStatus']['coding'][0]['code'])) {
            $attrs['is_active'] = $fhirResource['clinicalStatus']['coding'][0]['code'] === 'active';
        }

        if (isset($fhirResource['verificationStatus']['coding'][0]['code'])) {
            $reverseMap = array_flip(self::VERIFICATION_STATUS_MAP);
            $attrs['verification_status'] = $reverseMap[$fhirResource['verificationStatus']['coding'][0]['code']] ?? AllergyVerificationStatus::UNVERIFIED->value;
        }

        if (isset($fhirResource['code']['coding'][0]['display'])) {
            $attrs['allergen'] = $fhirResource['code']['coding'][0]['display'];
            $attrs['allergen_code'] = $fhirResource['code']['coding'][0]['code'] ?? null;
        } elseif (isset($fhirResource['code']['text'])) {
            $attrs['allergen'] = $fhirResource['code']['text'];
        }

        if (isset($fhirResource['category'][0])) {
            $reverseCategory = array_flip(self::CATEGORY_MAP);
            $attrs['allergen_type'] = $reverseCategory[$fhirResource['category'][0]] ?? AllergenType::OTHER->value;
        }

        if (isset($fhirResource['criticality'])) {
            $reverseCriticality = array_flip(self::SEVERITY_CRITICALITY_MAP);
            $criticalitySeverity = $reverseCriticality[$fhirResource['criticality']] ?? AllergySeverity::MODERATE->value;
            $attrs['severity'] = $criticalitySeverity;
        }

        if (isset($fhirResource['patient']['reference'])) {
            $parts = explode('/', $fhirResource['patient']['reference']);
            $attrs['patient_id'] = end($parts);
        }

        if (isset($fhirResource['reaction'][0]['manifestation'][0]['text'])) {
            $attrs['reaction'] = $fhirResource['reaction'][0]['manifestation'][0]['text'];
        }

        if (isset($fhirResource['reaction'][0]['severity'])) {
            $reverseSeverity = array_flip(self::SEVERITY_MAP);
            if (! isset($attrs['severity'])) {
                $attrs['severity'] = $reverseSeverity[$fhirResource['reaction'][0]['severity']] ?? AllergySeverity::MODERATE->value;
            }
        }

        if (isset($fhirResource['note'][0]['text'])) {
            $attrs['notes'] = $fhirResource['note'][0]['text'];
        }

        if (isset($fhirResource['onsetDateTime'])) {
            $attrs['onset_date'] = $fhirResource['onsetDateTime'];
        }

        if (isset($fhirResource['recordedDate'])) {
            $attrs['created_at'] = $fhirResource['recordedDate'];
        }

        return $attrs;
    }

    public function findById(string $id): ?Model
    {
        return Allergy::with([
            'patient',
            'verifiedBy',
        ])->find($id);
    }

    public function query(): Builder
    {
        return Allergy::with([
            'patient',
            'verifiedBy',
        ]);
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'patient' => ['column' => 'patient_id'],
            'clinical-status' => ['column' => 'is_active'],
            'verification-status' => ['column' => 'verification_status'],
            'category' => ['column' => 'allergen_type'],
            'severity' => ['column' => 'severity'],
            'onset-date' => ['column' => 'onset_date'],
            'recorded-date' => ['column' => 'created_at'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        $errors = [];

        if (! isset($fhirResource['patient']['reference'])) {
            $errors['ai-1'] = 'AllergyIntolerance SHALL have a patient.';
        }

        if (! isset($fhirResource['code']['coding'][0]['code']) && ! isset($fhirResource['code']['text'])) {
            $errors['ai-2'] = 'AllergyIntolerance SHALL have a code.';
        }

        $validVerificationStatuses = ['unconfirmed', 'confirmed', 'refuted', 'entered-in-error'];
        if (isset($fhirResource['verificationStatus']['coding'][0]['code'])
            && ! in_array($fhirResource['verificationStatus']['coding'][0]['code'], $validVerificationStatuses, true)) {
            $errors['ai-3'] = 'AllergyIntolerance verificationStatus is invalid.';
        }

        return $errors;
    }
}
