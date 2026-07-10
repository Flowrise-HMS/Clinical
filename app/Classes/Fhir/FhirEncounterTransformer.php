<?php

namespace Modules\Clinical\Classes\Fhir;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterParticipant;
use Modules\FHIR\Contracts\FhirResourceContract;

class FhirEncounterTransformer implements FhirResourceContract
{
    private const STATUS_MAP = [
        EncounterStatus::PLANNED->value => 'planned',
        EncounterStatus::ARRIVED->value => 'in-progress',
        EncounterStatus::TRIAGED->value => 'in-progress',
        EncounterStatus::IN_PROGRESS->value => 'in-progress',
        EncounterStatus::ON_LEAVE->value => 'on-hold',
        EncounterStatus::FINISHED->value => 'completed',
        EncounterStatus::CANCELLED->value => 'cancelled',
    ];

    private const CLASS_MAP = [
        EncounterType::INPATIENT->value => ['code' => 'IMP', 'display' => 'inpatient encounter'],
        EncounterType::OUTPATIENT->value => ['code' => 'AMB', 'display' => 'ambulatory'],
        EncounterType::EMERGENCY->value => ['code' => 'EMER', 'display' => 'emergency'],
        EncounterType::VIRTUAL->value => ['code' => 'VR', 'display' => 'virtual'],
        EncounterType::HOME_VISIT->value => ['code' => 'HH', 'display' => 'home health'],
    ];

    private const CLASS_SYSTEM = 'http://terminology.hl7.org/CodeSystem/v3-ActCode';

    public function resourceType(): string
    {
        return 'Encounter';
    }

    public function toFhir(Model $model): array
    {
        $resource = [
            'resourceType' => 'Encounter',
            'id' => $model->id,
            'identifier' => [
                [
                    'system' => 'http://flowrise.app/CodeSystem/encounter-number',
                    'value' => $model->encounter_number,
                ],
            ],
            'status' => self::STATUS_MAP[$model->status->value] ?? 'unknown',
        ];

        $classMapping = self::CLASS_MAP[$model->type->value] ?? null;
        if ($classMapping) {
            $resource['class'] = [
                [
                    'coding' => [
                        [
                            'system' => self::CLASS_SYSTEM,
                            'code' => $classMapping['code'],
                            'display' => $classMapping['display'],
                        ],
                    ],
                    'text' => $model->type->getLabel(),
                ],
            ];
        }

        if ($model->priority) {
            $resource['priority'] = [
                'coding' => [
                    [
                        'system' => 'http://flowrise.app/CodeSystem/encounter-priority',
                        'code' => $model->priority->value,
                        'display' => $model->priority->getLabel(),
                    ],
                ],
                'text' => $model->priority->getLabel(),
            ];
        }

        if ($model->patient_id) {
            $resource['subject'] = [
                'reference' => "Patient/{$model->patient_id}",
            ];
        }

        if ($model->admitted_at) {
            $resource['actualPeriod']['start'] = $model->admitted_at->toIso8601String();
        }

        if ($model->discharged_at) {
            $resource['actualPeriod']['end'] = $model->discharged_at->toIso8601String();
        }

        if ($model->branch_id) {
            $resource['serviceProvider'] = [
                'reference' => "Organization/{$model->branch_id}",
            ];
        }

        if ($model->department_id) {
            $resource['serviceType'] = [
                [
                    'concept' => [
                        'coding' => [
                            [
                                'system' => 'http://flowrise.app/CodeSystem/service-category',
                                'code' => $model->department_id,
                            ],
                        ],
                    ],
                    'reference' => [
                        'reference' => "HealthcareService/{$model->department_id}",
                    ],
                ],
            ];
        }

        if ($model->location_id) {
            $resource['location'][] = [
                'location' => [
                    'reference' => "Location/{$model->location_id}",
                ],
            ];

            if ($model->type === EncounterType::INPATIENT && $model->bed_id) {
                $resource['location'][] = [
                    'location' => [
                        'reference' => "Location/{$model->bed_id}",
                    ],
                ];
            }
        }

        if ($model->chief_complaint) {
            $resource['reason'][] = [
                'value' => [
                    [
                        'concept' => [
                            'text' => $model->chief_complaint,
                        ],
                    ],
                ],
            ];
        }

        $participants = $model->participants;
        if ($participants->isNotEmpty()) {
            foreach ($participants as $participant) {
                $entry = $this->buildParticipant($participant);
                if ($entry !== null) {
                    $resource['participant'][] = $entry;
                }
            }
        }

        $admission = $this->buildAdmission($model);
        if ($admission !== null) {
            $resource['admission'] = $admission;
        }

        return $resource;
    }

    private function buildParticipant(EncounterParticipant $participant): ?array
    {
        $user = $participant->user;
        if (! $user) {
            return null;
        }

        $entry = [];

        if ($participant->role) {
            $participationCode = match ($participant->role->value) {
                'attending', 'primary_provider', 'consultant' => 'ATND',
                'nurse' => 'NURSE',
                'resident', 'intern' => 'RES',
                default => 'PPRF',
            };

            $entry['type'] = [
                [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/v3-ParticipationType',
                            'code' => $participationCode,
                            'display' => $participant->role->getLabel(),
                        ],
                    ],
                ],
            ];
        }

        $entry['actor'] = [
            'reference' => "Practitioner/{$user->id}",
            'display' => $user->name,
        ];

        if ($participant->joined_at) {
            $entry['period']['start'] = $participant->joined_at instanceof DateTime
                ? $participant->joined_at->toIso8601String()
                : Carbon::parse($participant->joined_at)->toIso8601String();
        }

        if ($participant->left_at) {
            $entry['period']['end'] = $participant->left_at instanceof DateTime
                ? $participant->left_at->toIso8601String()
                : Carbon::parse($participant->left_at)->toIso8601String();
        }

        return $entry;
    }

    private function buildAdmission(Encounter $encounter): ?array
    {
        $admission = [];

        if ($encounter->discharge_disposition) {
            $admission['dischargeDisposition'] = [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                        'code' => $encounter->discharge_disposition->value,
                        'display' => $encounter->discharge_disposition->getLabel(),
                    ],
                ],
            ];
        }

        if ($encounter->transfer_destination) {
            $admission['destination'] = [
                'reference' => "Organization/{$encounter->transfer_destination}",
            ];
        }

        return empty($admission) ? null : $admission;
    }

    public function fromFhir(array $fhirResource): array
    {
        $attrs = [];

        if (isset($fhirResource['class'][0]['coding'][0]['code'])) {
            $code = $fhirResource['class'][0]['coding'][0]['code'];
            $reverseClassMap = array_combine(
                array_column(self::CLASS_MAP, 'code'),
                array_keys(self::CLASS_MAP)
            );
            if (isset($reverseClassMap[$code])) {
                $attrs['type'] = $reverseClassMap[$code];
            }
        }

        if (isset($fhirResource['status'])) {
            $reverseMap = array_flip(self::STATUS_MAP);
            $attrs['status'] = $reverseMap[$fhirResource['status']] ?? null;
        }

        if (isset($fhirResource['subject']['reference'])) {
            $parts = explode('/', $fhirResource['subject']['reference']);
            $attrs['patient_id'] = end($parts);
        }

        if (isset($fhirResource['actualPeriod']['start'])) {
            $attrs['admitted_at'] = $fhirResource['actualPeriod']['start'];
        }

        if (isset($fhirResource['actualPeriod']['end'])) {
            $attrs['discharged_at'] = $fhirResource['actualPeriod']['end'];
        }

        if (isset($fhirResource['serviceProvider']['reference'])) {
            $parts = explode('/', $fhirResource['serviceProvider']['reference']);
            $attrs['branch_id'] = end($parts);
        }

        if (isset($fhirResource['serviceType'][0]['reference']['reference'])) {
            $parts = explode('/', $fhirResource['serviceType'][0]['reference']['reference']);
            $attrs['department_id'] = end($parts);
        }

        if (isset($fhirResource['location'][0]['location']['reference'])) {
            $parts = explode('/', $fhirResource['location'][0]['location']['reference']);
            $attrs['location_id'] = end($parts);
        }

        if (isset($fhirResource['reason'][0]['value'][0]['concept']['text'])) {
            $attrs['chief_complaint'] = $fhirResource['reason'][0]['value'][0]['concept']['text'];
        }

        if (isset($fhirResource['priority']['coding'][0]['code'])) {
            $attrs['priority'] = $fhirResource['priority']['coding'][0]['code'];
        }

        if (isset($fhirResource['admission']['dischargeDisposition']['coding'][0]['code'])) {
            $attrs['discharge_disposition'] = $fhirResource['admission']['dischargeDisposition']['coding'][0]['code'];
        }

        if (isset($fhirResource['admission']['destination']['reference'])) {
            $parts = explode('/', $fhirResource['admission']['destination']['reference']);
            $attrs['transfer_destination'] = end($parts);
        }

        return $attrs;
    }

    public function findById(string $id): ?Model
    {
        return Encounter::with(['participants.user.practitioner', 'branch', 'department', 'location', 'bed'])->find($id);
    }

    public function query(): Builder
    {
        return Encounter::with(['participants.user.practitioner', 'branch', 'department', 'location', 'bed']);
    }

    public function searchableParameters(): array
    {
        return [
            '_id' => ['column' => 'id'],
            'status' => ['column' => 'status'],
            'subject' => ['column' => 'patient_id'],
            'subject.identifier' => ['column' => 'patient_id'],
            'date' => ['column' => 'admitted_at'],
            'location' => ['column' => 'location_id'],
            'service-provider' => ['column' => 'branch_id'],
            'service-type' => ['column' => 'department_id'],
        ];
    }

    public function validateBusinessRules(array $fhirResource): array
    {
        $errors = [];

        $validStatuses = ['planned', 'in-progress', 'on-hold', 'discharged', 'completed', 'cancelled', 'discontinued', 'entered-in-error', 'unknown'];
        if (! isset($fhirResource['status']) || ! in_array($fhirResource['status'], $validStatuses, true)) {
            $errors['enc-1'] = 'Encounter SHALL have a valid status.';
        }

        if (! isset($fhirResource['subject']['reference'])) {
            $errors['enc-2'] = 'Encounter SHALL have a subject (patient) reference.';
        }

        return $errors;
    }
}
