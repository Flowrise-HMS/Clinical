<?php

use Tests\TestCase;

uses(TestCase::class);

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Fhir\FhirEncounterTransformer;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterParticipant;
use Modules\FHIR\Contracts\FhirResourceContract;

$transformer = new FhirEncounterTransformer;

test('implements FhirResourceContract', function () use ($transformer) {
    expect($transformer)->toBeInstanceOf(FhirResourceContract::class);
});

test('resourceType returns Encounter', function () use ($transformer) {
    expect($transformer->resourceType())->toBe('Encounter');
});

test('toFhir contains required fields', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-0001-aaaa-bbbb-cccc';
    $encounter->encounter_number = 'ENC-20260709-0001';
    $encounter->status = EncounterStatus::IN_PROGRESS;
    $encounter->type = EncounterType::OUTPATIENT;

    $fhir = $transformer->toFhir($encounter);

    expect($fhir)->toHaveKey('resourceType', 'Encounter');
    expect($fhir)->toHaveKey('id', $encounter->id);
    expect($fhir['identifier'][0]['value'])->toBe('ENC-20260709-0001');
    expect($fhir)->toHaveKey('status', 'in-progress');
});

test('toFhir maps all statuses correctly', function () use ($transformer) {
    $map = [
        EncounterStatus::PLANNED->value => 'planned',
        EncounterStatus::ARRIVED->value => 'in-progress',
        EncounterStatus::TRIAGED->value => 'in-progress',
        EncounterStatus::IN_PROGRESS->value => 'in-progress',
        EncounterStatus::ON_LEAVE->value => 'on-hold',
        EncounterStatus::FINISHED->value => 'completed',
        EncounterStatus::CANCELLED->value => 'cancelled',
    ];

    foreach ($map as $appStatus => $expectedFhirStatus) {
        $encounter = new Encounter;
        $encounter->id = 'enc-'.$appStatus;
        $encounter->encounter_number = 'ENC-'.$appStatus;
        $encounter->status = EncounterStatus::from($appStatus);
        $encounter->type = EncounterType::OUTPATIENT;

        $fhir = $transformer->toFhir($encounter);

        expect($fhir['status'])->toBe($expectedFhirStatus);
    }
});

test('toFhir maps all encounter types to class', function () use ($transformer) {
    $expected = [
        EncounterType::INPATIENT->value => ['code' => 'IMP', 'display' => 'inpatient encounter'],
        EncounterType::OUTPATIENT->value => ['code' => 'AMB', 'display' => 'ambulatory'],
        EncounterType::EMERGENCY->value => ['code' => 'EMER', 'display' => 'emergency'],
        EncounterType::VIRTUAL->value => ['code' => 'VR', 'display' => 'virtual'],
        EncounterType::HOME_VISIT->value => ['code' => 'HH', 'display' => 'home health'],
    ];

    foreach ($expected as $typeValue => $mapping) {
        $encounter = new Encounter;
        $encounter->id = 'enc-class-'.$typeValue;
        $encounter->encounter_number = 'ENC-CLASS-'.$typeValue;
        $encounter->type = EncounterType::from($typeValue);
        $encounter->status = EncounterStatus::PLANNED;

        $fhir = $transformer->toFhir($encounter);

        expect($fhir['class'][0]['coding'][0]['system'])->toBe('http://terminology.hl7.org/CodeSystem/v3-ActCode');
        expect($fhir['class'][0]['coding'][0]['code'])->toBe($mapping['code']);
        expect($fhir['class'][0]['coding'][0]['display'])->toBe($mapping['display']);
    }
});

test('toFhir maps priority', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-pri-1';
    $encounter->encounter_number = 'ENC-PRI-1';
    $encounter->status = EncounterStatus::TRIAGED;
    $encounter->type = EncounterType::EMERGENCY;
    $encounter->priority = EncounterPriority::EMERGENCY;

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['priority']['coding'][0]['code'])->toBe('emergency');
    expect($fhir['priority']['text'])->toBe('Emergency');
});

test('toFhir maps subject to patient reference', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-sub-1';
    $encounter->encounter_number = 'ENC-SUB-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;
    $encounter->patient_id = 'patient-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['subject']['reference'])->toBe('Patient/patient-uuid');
});

test('toFhir omits subject when no patient', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-nosub-1';
    $encounter->encounter_number = 'ENC-NOSUB-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;

    $fhir = $transformer->toFhir($encounter);

    expect($fhir)->not->toHaveKey('subject');
});

test('toFhir maps actualPeriod from admitted_at and discharged_at', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-per-1';
    $encounter->encounter_number = 'ENC-PER-1';
    $encounter->status = EncounterStatus::FINISHED;
    $encounter->type = EncounterType::INPATIENT;
    $encounter->admitted_at = now()->subDays(3);
    $encounter->discharged_at = now();

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['actualPeriod']['start'])->toBe($encounter->admitted_at->toIso8601String());
    expect($fhir['actualPeriod']['end'])->toBe($encounter->discharged_at->toIso8601String());
});

test('toFhir omits actualPeriod when no dates', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-noper-1';
    $encounter->encounter_number = 'ENC-NOPER-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;

    $fhir = $transformer->toFhir($encounter);

    expect($fhir)->not->toHaveKey('actualPeriod');
});

test('toFhir maps serviceProvider to branch', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-sp-1';
    $encounter->encounter_number = 'ENC-SP-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;
    $encounter->branch_id = 'branch-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['serviceProvider']['reference'])->toBe('Organization/branch-uuid');
});

test('toFhir maps serviceType to department/healthcareservice', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-st-1';
    $encounter->encounter_number = 'ENC-ST-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;
    $encounter->department_id = 'dept-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['serviceType'][0]['reference']['reference'])->toBe('HealthcareService/dept-uuid');
});

test('toFhir maps location references', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-loc-1';
    $encounter->encounter_number = 'ENC-LOC-1';
    $encounter->status = EncounterStatus::IN_PROGRESS;
    $encounter->type = EncounterType::OUTPATIENT;
    $encounter->location_id = 'loc-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['location'][0]['location']['reference'])->toBe('Location/loc-uuid');
});

test('toFhir includes bed location for inpatients', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-bed-1';
    $encounter->encounter_number = 'ENC-BED-1';
    $encounter->status = EncounterStatus::IN_PROGRESS;
    $encounter->type = EncounterType::INPATIENT;
    $encounter->location_id = 'ward-uuid';
    $encounter->bed_id = 'bed-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['location'][0]['location']['reference'])->toBe('Location/ward-uuid');
    expect($fhir['location'][1]['location']['reference'])->toBe('Location/bed-uuid');
});

test('toFhir maps chief_complaint to reason', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-reas-1';
    $encounter->encounter_number = 'ENC-REAS-1';
    $encounter->status = EncounterStatus::TRIAGED;
    $encounter->type = EncounterType::EMERGENCY;
    $encounter->chief_complaint = 'Chest pain and shortness of breath';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['reason'][0]['value'][0]['concept']['text'])->toBe('Chest pain and shortness of breath');
});

test('toFhir maps participants', function () use ($transformer) {
    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('getAttribute')->with('id')->andReturn('user-1');
    $user->shouldReceive('getAttribute')->with('name')->andReturn('Dr. Smith');

    $participant = new EncounterParticipant;
    $participant->id = 'ep-1';
    $participant->role = ParticipantRole::ATTENDING;
    $participant->joined_at = now()->subHour();
    $participant->left_at = now();
    $participant->setRelation('user', $user);

    $encounter = new Encounter;
    $encounter->id = 'enc-part-1';
    $encounter->encounter_number = 'ENC-PART-1';
    $encounter->status = EncounterStatus::FINISHED;
    $encounter->type = EncounterType::OUTPATIENT;
    $encounter->setRelation('participants', new Collection([$participant]));

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['participant'][0]['actor']['reference'])->toBe('Practitioner/user-1');
    expect($fhir['participant'][0]['actor']['display'])->toBe('Dr. Smith');
    expect($fhir['participant'][0]['type'][0]['coding'][0]['code'])->toBe('ATND');
    expect($fhir['participant'][0]['period']['start'])->not->toBeNull();
    expect($fhir['participant'][0]['period']['end'])->not->toBeNull();
});

test('toFhir maps admission details', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-adm-1';
    $encounter->encounter_number = 'ENC-ADM-1';
    $encounter->status = EncounterStatus::FINISHED;
    $encounter->type = EncounterType::INPATIENT;
    $encounter->discharge_disposition = DischargeDisposition::COMPLETED;
    $encounter->transfer_destination = 'other-branch-uuid';

    $fhir = $transformer->toFhir($encounter);

    expect($fhir['admission']['dischargeDisposition']['coding'][0]['code'])->toBe('completed');
    expect($fhir['admission']['destination']['reference'])->toBe('Organization/other-branch-uuid');
});

test('toFhir omits admission when no discharge details', function () use ($transformer) {
    $encounter = new Encounter;
    $encounter->id = 'enc-noadm-1';
    $encounter->encounter_number = 'ENC-NADM-1';
    $encounter->status = EncounterStatus::PLANNED;
    $encounter->type = EncounterType::OUTPATIENT;

    $fhir = $transformer->toFhir($encounter);

    expect($fhir)->not->toHaveKey('admission');
});

test('fromFhir extracts attributes correctly', function () use ($transformer) {
    $fhirResource = [
        'resourceType' => 'Encounter',
        'status' => 'in-progress',
        'class' => [
            [
                'coding' => [
                    ['system' => 'http://terminology.hl7.org/CodeSystem/v3-ActCode', 'code' => 'AMB', 'display' => 'ambulatory'],
                ],
            ],
        ],
        'subject' => ['reference' => 'Patient/patient-uuid'],
        'serviceProvider' => ['reference' => 'Organization/branch-uuid'],
        'serviceType' => [
            [
                'reference' => ['reference' => 'HealthcareService/dept-uuid'],
            ],
        ],
        'actualPeriod' => ['start' => '2026-07-09T08:00:00+00:00', 'end' => '2026-07-09T10:00:00+00:00'],
        'reason' => [
            [
                'value' => [
                    ['concept' => ['text' => 'Routine checkup']],
                ],
            ],
        ],
        'admission' => [
            'dischargeDisposition' => [
                'coding' => [
                    ['system' => 'http://terminology.hl7.org/CodeSystem/discharge-disposition', 'code' => 'completed', 'display' => 'Completed'],
                ],
            ],
        ],
        'location' => [
            ['location' => ['reference' => 'Location/loc-uuid']],
        ],
    ];

    $attrs = $transformer->fromFhir($fhirResource);

    expect($attrs)->toHaveKey('status', 'in_progress');
    expect($attrs)->toHaveKey('type', 'outpatient');
    expect($attrs)->toHaveKey('patient_id', 'patient-uuid');
    expect($attrs)->toHaveKey('branch_id', 'branch-uuid');
    expect($attrs)->toHaveKey('department_id', 'dept-uuid');
    expect($attrs)->toHaveKey('location_id', 'loc-uuid');
    expect($attrs)->toHaveKey('admitted_at', '2026-07-09T08:00:00+00:00');
    expect($attrs)->toHaveKey('discharged_at', '2026-07-09T10:00:00+00:00');
    expect($attrs)->toHaveKey('chief_complaint', 'Routine checkup');
    expect($attrs)->toHaveKey('discharge_disposition', 'completed');
});

test('fromFhir handles minimal resource', function () use ($transformer) {
    $fhirResource = [
        'resourceType' => 'Encounter',
        'status' => 'planned',
        'subject' => ['reference' => 'Patient/patient-uuid'],
    ];

    $attrs = $transformer->fromFhir($fhirResource);

    expect($attrs)->toHaveKey('status', 'planned');
    expect($attrs)->toHaveKey('patient_id', 'patient-uuid');
    expect($attrs)->not->toHaveKey('chief_complaint');
    expect($attrs)->not->toHaveKey('branch_id');
});

test('searchableParameters has expected keys', function () use ($transformer) {
    $params = $transformer->searchableParameters();

    expect($params)->toHaveKeys(['_id', 'status', 'subject', 'date', 'location', 'service-provider', 'service-type']);
    expect($params['status'])->toHaveKey('column', 'status');
    expect($params['subject'])->toHaveKey('column', 'patient_id');
});

test('validateBusinessRules passes with status and subject', function () use ($transformer) {
    $resource = ['resourceType' => 'Encounter', 'status' => 'planned', 'subject' => ['reference' => 'Patient/uuid']];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toBeEmpty();
});

test('validateBusinessRules fails without valid status', function () use ($transformer) {
    $resource = ['resourceType' => 'Encounter', 'status' => 'bogus'];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toHaveKey('enc-1');
});

test('validateBusinessRules fails without subject', function () use ($transformer) {
    $resource = ['resourceType' => 'Encounter', 'status' => 'planned'];

    $errors = $transformer->validateBusinessRules($resource);

    expect($errors)->toHaveKey('enc-2');
});
