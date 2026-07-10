<?php

use Modules\Clinical\Classes\Fhir\FhirAllergyIntoleranceTransformer;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Models\Allergy;
use Modules\FHIR\Contracts\FhirResourceContract;
use Tests\TestCase;

uses(TestCase::class);

function createAllergy(array $attrs = []): Allergy
{
    $model = new class extends Allergy
    {
        public $timestamps = false;
    };

    $model->setAttribute('id', '00000000-0000-0000-0000-000000000001');
    $model->setAttribute('patient_id', '00000000-0000-0000-0000-000000000010');
    $model->setAttribute('allergen', 'Peanuts');
    $model->setAttribute('allergen_code', 'C321.0');
    $model->setAttribute('allergen_type', AllergenType::FOOD);
    $model->setAttribute('reaction', 'Anaphylaxis');
    $model->setAttribute('severity', AllergySeverity::SEVERE);
    $model->setAttribute('is_active', true);
    $model->setAttribute('verification_status', AllergyVerificationStatus::VERIFIED);
    $model->setAttribute('notes', 'Patient carries EpiPen');

    foreach ($attrs as $key => $value) {
        $model->setAttribute($key, $value);
    }

    return $model;
}

test('implements FhirResourceContract', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    expect($transformer)->toBeInstanceOf(FhirResourceContract::class);
});

test('resourceType returns AllergyIntolerance', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    expect($transformer->resourceType())->toBe('AllergyIntolerance');
});

test('toFhir contains required fields', function () {
    $model = createAllergy(['created_at' => now()]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['resourceType'])->toBe('AllergyIntolerance')
        ->and($result['id'])->toBe($model->id)
        ->and($result['code']['coding'][0]['code'])->toBe('C321.0')
        ->and($result['code']['text'])->toBe('Peanuts')
        ->and($result['patient']['reference'])->toBe('Patient/00000000-0000-0000-0000-000000000010');
});

test('toFhir maps clinicalStatus based on is_active', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $active = createAllergy(['is_active' => true]);
    $inactive = createAllergy(['is_active' => false]);

    $activeResult = $transformer->toFhir($active);
    $inactiveResult = $transformer->toFhir($inactive);

    expect($activeResult['clinicalStatus']['coding'][0]['code'])->toBe('active');
    expect($inactiveResult['clinicalStatus']['coding'][0]['code'])->toBe('inactive');
});

test('toFhir maps verificationStatus correctly', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $verified = createAllergy(['verification_status' => AllergyVerificationStatus::VERIFIED]);
    $unverified = createAllergy(['verification_status' => AllergyVerificationStatus::UNVERIFIED]);
    $refuted = createAllergy(['verification_status' => AllergyVerificationStatus::REFUTED]);

    expect($transformer->toFhir($verified)['verificationStatus']['coding'][0]['code'])->toBe('confirmed');
    expect($transformer->toFhir($unverified)['verificationStatus']['coding'][0]['code'])->toBe('unconfirmed');
    expect($transformer->toFhir($refuted)['verificationStatus']['coding'][0]['code'])->toBe('refuted');
});

test('toFhir maps category based on allergen_type', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $food = createAllergy(['allergen_type' => AllergenType::FOOD]);
    $medication = createAllergy(['allergen_type' => AllergenType::MEDICATION]);
    $environmental = createAllergy(['allergen_type' => AllergenType::ENVIRONMENTAL]);
    $biologic = createAllergy(['allergen_type' => AllergenType::BIOLOGICAL]);
    $other = createAllergy(['allergen_type' => AllergenType::OTHER]);

    expect($transformer->toFhir($food)['category'][0])->toBe('food');
    expect($transformer->toFhir($medication)['category'][0])->toBe('medication');
    expect($transformer->toFhir($environmental)['category'][0])->toBe('environment');
    expect($transformer->toFhir($biologic)['category'][0])->toBe('biologic');
    expect($transformer->toFhir($other)['category'][0])->toBe('environment');
});

test('toFhir maps severity to criticality', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $mild = createAllergy(['severity' => AllergySeverity::MILD]);
    $moderate = createAllergy(['severity' => AllergySeverity::MODERATE]);
    $severe = createAllergy(['severity' => AllergySeverity::SEVERE]);
    $lifeThreatening = createAllergy(['severity' => AllergySeverity::LIFE_THREATENING]);

    expect($transformer->toFhir($mild)['criticality'])->toBe('low');
    expect($transformer->toFhir($moderate)['criticality'])->toBe('low');
    expect($transformer->toFhir($severe)['criticality'])->toBe('high');
    expect($transformer->toFhir($lifeThreatening)['criticality'])->toBe('high');
});

test('toFhir maps severity to reaction.severity', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $mild = createAllergy(['severity' => AllergySeverity::MILD]);
    $moderate = createAllergy(['severity' => AllergySeverity::MODERATE]);
    $severe = createAllergy(['severity' => AllergySeverity::SEVERE]);
    $lifeThreatening = createAllergy(['severity' => AllergySeverity::LIFE_THREATENING]);

    expect($transformer->toFhir($mild)['reaction'][0]['severity'])->toBe('mild');
    expect($transformer->toFhir($moderate)['reaction'][0]['severity'])->toBe('moderate');
    expect($transformer->toFhir($severe)['reaction'][0]['severity'])->toBe('severe');
    expect($transformer->toFhir($lifeThreatening)['reaction'][0]['severity'])->toBe('severe');
});

test('toFhir includes reaction with manifestation', function () {
    $model = createAllergy();
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['reaction'][0]['manifestation'][0]['text'])->toBe('Anaphylaxis');
});

test('toFhir omits reaction when no reaction or severity', function () {
    $model = createAllergy(['reaction' => null, 'severity' => null]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result)->not->toHaveKey('reaction');
});

test('toFhir omits criticality when no severity', function () {
    $model = createAllergy(['severity' => null]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result)->not->toHaveKey('criticality');
});

test('toFhir maps onsetDateTime when onset_date is set', function () {
    $onsetDate = now()->subDays(30);
    $model = createAllergy(['onset_date' => $onsetDate]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['onsetDateTime'])->toBe($onsetDate->startOfDay()->toIso8601String());
});

test('toFhir maps lastOccurrence when verified_at is set', function () {
    $verifiedAt = now()->subDays(1);
    $model = createAllergy(['verified_at' => $verifiedAt]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['lastOccurrence'])->toBe($verifiedAt->toIso8601String());
});

test('toFhir includes note when notes are set', function () {
    $model = createAllergy();
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['note'][0]['text'])->toBe('Patient carries EpiPen');
});

test('toFhir omits note when no notes', function () {
    $model = createAllergy(['notes' => null]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result)->not->toHaveKey('note');
});

test('toFhir uses unknown code when allergen_code is null', function () {
    $model = createAllergy(['allergen_code' => null]);
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->toFhir($model);

    expect($result['code']['coding'][0]['code'])->toBe('unknown');
});

test('fromFhir extracts attributes correctly', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->fromFhir([
        'resourceType' => 'AllergyIntolerance',
        'clinicalStatus' => [
            'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical', 'code' => 'active']],
        ],
        'verificationStatus' => [
            'coding' => [['system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification', 'code' => 'confirmed']],
        ],
        'category' => ['food'],
        'code' => [
            'coding' => [['code' => 'T78.0', 'display' => 'Food allergy']],
        ],
        'criticality' => 'high',
        'patient' => ['reference' => 'Patient/00000000-0000-0000-0000-000000000010'],
        'reaction' => [
            [
                'manifestation' => [['text' => 'Hives']],
                'severity' => 'moderate',
            ],
        ],
        'note' => [['text' => 'Avoid peanuts']],
        'onsetDateTime' => '2025-01-01T00:00:00+00:00',
        'recordedDate' => '2025-01-15T10:30:00+00:00',
    ]);

    expect($result['is_active'])->toBeTrue()
        ->and($result['verification_status'])->toBe(AllergyVerificationStatus::VERIFIED->value)
        ->and($result['allergen'])->toBe('Food allergy')
        ->and($result['allergen_code'])->toBe('T78.0')
        ->and($result['allergen_type'])->toBe(AllergenType::FOOD->value)
        ->and($result['severity'])->toBe(AllergySeverity::LIFE_THREATENING->value)
        ->and($result['patient_id'])->toBe('00000000-0000-0000-0000-000000000010')
        ->and($result['reaction'])->toBe('Hives')
        ->and($result['notes'])->toBe('Avoid peanuts')
        ->and($result['onset_date'])->toBe('2025-01-01T00:00:00+00:00')
        ->and($result['created_at'])->toBe('2025-01-15T10:30:00+00:00');
});

test('fromFhir handles minimal resource', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $result = $transformer->fromFhir([
        'resourceType' => 'AllergyIntolerance',
        'patient' => ['reference' => 'Patient/xyz'],
        'code' => ['text' => 'Latex allergy'],
    ]);

    expect($result['allergen'])->toBe('Latex allergy');
});

test('searchableParameters has expected keys', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $params = $transformer->searchableParameters();

    expect($params)->toHaveKeys(['_id', 'patient', 'clinical-status', 'verification-status', 'category', 'severity', 'onset-date', 'recorded-date']);
});

test('validateBusinessRules passes with valid data', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'AllergyIntolerance',
        'patient' => ['reference' => 'Patient/1'],
        'code' => ['coding' => [['code' => 'T78.0']]],
    ]);

    expect($errors)->toBeEmpty();
});

test('validateBusinessRules fails without patient', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'AllergyIntolerance',
        'code' => ['coding' => [['code' => 'T78.0']]],
    ]);

    expect($errors)->toHaveKey('ai-1');
});

test('validateBusinessRules fails without code', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'AllergyIntolerance',
        'patient' => ['reference' => 'Patient/1'],
    ]);

    expect($errors)->toHaveKey('ai-2');
});

test('validateBusinessRules fails with invalid verificationStatus', function () {
    $transformer = new FhirAllergyIntoleranceTransformer;

    $errors = $transformer->validateBusinessRules([
        'resourceType' => 'AllergyIntolerance',
        'patient' => ['reference' => 'Patient/1'],
        'code' => ['coding' => [['code' => 'T78.0']]],
        'verificationStatus' => ['coding' => [['code' => 'invalid']]],
    ]);

    expect($errors)->toHaveKey('ai-3');
});
