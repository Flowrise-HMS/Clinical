<?php

use Modules\Clinical\Classes\Fhir\FhirConditionTransformer;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\FHIR\Contracts\FhirResourceContract;
use Tests\TestCase;

uses(TestCase::class);

function createEncounterDiagnosis(array $attrs = []): EncounterDiagnosis
{
    $model = new class extends EncounterDiagnosis
    {
        public $timestamps = false;

        protected $table = 'encounter_diagnoses';
    };

    $model->setAttribute('id', '00000000-0000-0000-0000-000000000001');
    $model->setAttribute('patient_id', '00000000-0000-0000-0000-000000000010');
    $model->setAttribute('encounter_id', '00000000-0000-0000-0000-000000000020');
    $model->setAttribute('icd_code', 'J45.0');
    $model->setAttribute('description', 'Mild intermittent asthma');
    $model->setAttribute('is_active', true);
    $model->setAttribute('type', 'encounter-diagnosis');
    $model->setAttribute('notes', 'Patient reports wheezing');

    foreach ($attrs as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

test('implements FhirResourceContract', function () {
    $transformer = new FhirConditionTransformer;

    expect($transformer)->toBeInstanceOf(FhirResourceContract::class);
});

test('resourceType returns Condition', function () {
    $transformer = new FhirConditionTransformer;

    expect($transformer->resourceType())->toBe('Condition');
});

test('toFhir contains required fields', function () {
    $model = createEncounterDiagnosis(['created_at' => now()]);
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['resourceType'])->toBe('Condition')
        ->and($result['id'])->toBe($model->id)
        ->and($result['code']['coding'][0]['code'])->toBe('J45.0')
        ->and($result['code']['text'])->toBe('Mild intermittent asthma')
        ->and($result['subject']['reference'])->toBe('Patient/00000000-0000-0000-0000-000000000010')
        ->and($result['encounter']['reference'])->toBe('Encounter/00000000-0000-0000-0000-000000000020');
});

test('toFhir maps clinicalStatus based on is_active', function () {
    $transformer = new FhirConditionTransformer;

    $active = createEncounterDiagnosis(['is_active' => true]);
    $inactive = createEncounterDiagnosis(['is_active' => false]);

    $activeResult = $transformer->toFhir($active);
    $inactiveResult = $transformer->toFhir($inactive);

    expect($activeResult['clinicalStatus']['coding'][0]['code'])->toBe('active');
    expect($inactiveResult['clinicalStatus']['coding'][0]['code'])->toBe('inactive');
});

test('toFhir maps category as encounter-diagnosis', function () {
    $model = createEncounterDiagnosis();
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['category'][0]['coding'][0]['code'])->toBe('encounter-diagnosis');
});

test('toFhir maps verificationStatus as confirmed', function () {
    $model = createEncounterDiagnosis();
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['verificationStatus']['coding'][0]['code'])->toBe('confirmed');
});

test('toFhir maps recordedDate when created_at is set', function () {
    $now = now();
    $model = createEncounterDiagnosis(['created_at' => $now]);
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['recordedDate'])->toBe($now->toIso8601String());
});

test('toFhir omits recordedDate when created_at is null', function () {
    $model = createEncounterDiagnosis(['created_at' => null]);
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result)->not->toHaveKey('recordedDate');
});

test('toFhir includes note when notes are set', function () {
    $model = createEncounterDiagnosis();
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['note'][0]['text'])->toBe('Patient reports wheezing');
});

test('toFhir omits note when no notes', function () {
    $model = createEncounterDiagnosis(['notes' => null]);
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result)->not->toHaveKey('note');
});

test('toFhir uses unknown code when icd_code is null', function () {
    $model = createEncounterDiagnosis(['icd_code' => null]);
    $transformer = new FhirConditionTransformer;

    $result = $transformer->toFhir($model);

    expect($result['code']['coding'][0]['code'])->toBe('unknown');
});

test('fromFhir extracts attributes correctly', function () {
    $transformer = new FhirConditionTransformer;

    $result = $transformer->fromFhir([
        'resourceType' => 'Condition',
        'clinicalStatus' => [
            'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical', 'code' => 'inactive']],
        ],
        'code' => [
            'coding' => [['system' => 'http://hl7.org/fhir/sid/icd-10-CM', 'code' => 'I10', 'display' => 'Essential hypertension']],
        ],
        'subject' => ['reference' => 'Patient/00000000-0000-0000-0000-000000000010'],
        'encounter' => ['reference' => 'Encounter/00000000-0000-0000-0000-000000000020'],
        'category' => [
            ['coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/condition-category', 'code' => 'encounter-diagnosis']]],
        ],
        'note' => [['text' => 'Follow up required']],
        'recordedDate' => '2025-01-15T10:30:00+00:00',
    ]);

    expect($result['is_active'])->toBeFalse()
        ->and($result['icd_code'])->toBe('I10')
        ->and($result['description'])->toBe('Essential hypertension')
        ->and($result['patient_id'])->toBe('00000000-0000-0000-0000-000000000010')
        ->and($result['encounter_id'])->toBe('00000000-0000-0000-0000-000000000020')
        ->and($result['notes'])->toBe('Follow up required')
        ->and($result['created_at'])->toBe('2025-01-15T10:30:00+00:00');
});

test('fromFhir handles minimal resource', function () {
    $transformer = new FhirConditionTransformer;

    $result = $transformer->fromFhir([
        'resourceType' => 'Condition',
        'clinicalStatus' => [
            'coding' => [['code' => 'active']],
        ],
        'subject' => ['reference' => 'Patient/xyz'],
        'code' => ['text' => 'Unknown condition'],
    ]);

    expect($result['is_active'])->toBeTrue()
        ->and($result['description'])->toBe('Unknown condition')
        ->and($result)->not->toHaveKey('icd_code');
});

test('searchableParameters has expected keys', function () {
    $transformer = new FhirConditionTransformer;

    $params = $transformer->searchableParameters();

    expect($params)->toHaveKeys(['_id', 'subject', 'encounter', 'code', 'clinical-status', 'category', 'recorded-date']);
});

test('validateBusinessRules passes with valid data', function () {
    $transformer = new FhirConditionTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'Condition',
        'subject' => ['reference' => 'Patient/1'],
        'code' => ['coding' => [['code' => 'J45.0']]],
        'clinicalStatus' => ['coding' => [['code' => 'active']]],
    ]);

    expect($errors)->toBeEmpty();
});

test('validateBusinessRules fails without subject', function () {
    $transformer = new FhirConditionTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'Condition',
        'clinicalStatus' => ['coding' => [['code' => 'active']]],
    ]);

    expect($errors)->toHaveKey('con-1');
});

test('validateBusinessRules fails without code', function () {
    $transformer = new FhirConditionTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'Condition',
        'subject' => ['reference' => 'Patient/1'],
        'clinicalStatus' => ['coding' => [['code' => 'active']]],
    ]);

    expect($errors)->toHaveKey('con-2');
});

test('validateBusinessRules fails without clinicalStatus', function () {
    $transformer = new FhirConditionTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'Condition',
        'subject' => ['reference' => 'Patient/1'],
        'code' => ['coding' => [['code' => 'J45.0']]],
    ]);

    expect($errors)->toHaveKey('con-3');
});
