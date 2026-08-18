<?php

use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\DiagnosisCertainty;
use Modules\Clinical\Enums\DiagnosisType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\NoteStatus;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function expectedAdtFormDefaults(): array
{
    return [
        'ward_id' => null,
        'bed_id' => null,
        'notes' => null,
        'transfer_ward_id' => null,
        'transfer_bed_id' => null,
        'transfer_notes' => null,
        'destination_type' => AdtDestinationType::ExternalFacility->value,
        'destination_branch_id' => null,
        'destination_label' => null,
        'transfer_out_notes' => null,
        'admission_source' => 'local',
        'source_label' => null,
        'from_branch_id' => null,
        'transfer_in_chief_complaint' => null,
        'transfer_in_ward_id' => null,
        'transfer_in_bed_id' => null,
        'transfer_in_notes' => null,
    ];
}

/**
 * @return array<string, mixed>
 */
function expectedDiagnosisItemDefaults(): array
{
    return [
        'code_search' => null,
        'description' => null,
        'type' => DiagnosisType::Primary->value,
        'is_new_case' => '0',
        'certainty' => DiagnosisCertainty::Provisional->value,
        'notes' => null,
        'diagnosis_code_id' => null,
        'icd_entity_id' => null,
        'icd_uri' => null,
        'icd_code' => null,
        'icd10_code' => null,
    ];
}

/**
 * @return array<string, mixed>
 */
function expectedMedicationItemDefaults(): array
{
    return [
        'service_id' => null,
        'dosage' => null,
        'frequency' => null,
        'route' => null,
        'duration_days' => null,
        'instructions' => null,
        'prn' => false,
        'indication' => null,
        'refills' => 0,
    ];
}

it('defines nested form state keys for Livewire entangle', function (): void {
    $defaults = (new ReflectionClass(ClinicalWorkspace::class))->getDefaultProperties();

    expect($defaults['consultationData'])->toBe(['notes' => null])
        ->and($defaults['referralData'])->toBe(['destination' => null, 'notes' => null])
        ->and($defaults['noteFormData'])->toBe([
            'note_type' => null,
            'status' => NoteStatus::DRAFT->value,
            'subject' => null,
            'content' => null,
        ])
        ->and($defaults['dischargeData'])->toBe([
            'discharge_notes' => null,
            'discharge_disposition' => DischargeDisposition::COMPLETED->value,
            'transfer_destination' => null,
        ])
        ->and($defaults['adtFormData'])->toBe(expectedAdtFormDefaults())
        ->and($defaults['encounterFormData'])->toBe([
            'type' => EncounterType::OUTPATIENT->value,
            'coverage_type' => null,
            'claim_check_code' => null,
            'chief_complaint' => null,
        ])
        ->and($defaults['vitalsData'])->toHaveKeys([
            'systolic_bp',
            'diastolic_bp',
            'heart_rate',
            'temperature',
            'spo2',
            'respiratory_rate',
            'weight',
            'height',
            'calculated_bmi',
        ])
        ->and($defaults['allergyData'])->toMatchArray([
            'allergen_type' => null,
            'allergen_name' => null,
            'severity' => AllergySeverity::MILD->value,
            'verification_status' => AllergyVerificationStatus::VERIFIED->value,
            'onset_type' => OnsetType::ACUTE->value,
        ])
        ->and($defaults['serviceRequestData'])->toMatchArray([
            'priority' => RequestPriority::ROUTINE->value,
            'notes' => null,
            'items' => [],
            'request_item_id' => null,
        ])
        ->and($defaults['diagnosisFormData']['diagnoses'][0])->toBe(expectedDiagnosisItemDefaults())
        ->and($defaults['medicationData']['items'][0])->toBe(expectedMedicationItemDefaults());
});

it('restores nested form state keys when form states are reset', function (): void {
    $page = (new ReflectionClass(ClinicalWorkspace::class))->newInstanceWithoutConstructor();
    $page->consultationData = ['notes' => '<p>temp</p>'];
    $page->referralData = [];
    $page->noteFormData = [];
    $page->dischargeData = [];
    $page->adtFormData = [];
    $page->encounterFormData = ['foo' => 'bar'];
    $page->consultationChiefComplaint = 'pain';
    $page->consultationNotes = 'old';
    $page->diagnosisFormData = ['x' => 1];
    $page->vitalsData = ['y' => 1];
    $page->serviceRequestData = ['z' => 1];
    $page->labResultData = ['a' => 1];
    $page->allergyData = ['b' => 1];
    $page->medicationData = ['c' => 1];

    $method = new ReflectionMethod(ClinicalWorkspace::class, 'resetFormStates');
    $method->invoke($page);

    expect($page->consultationData)->toBe(['notes' => null])
        ->and($page->referralData)->toBe(['destination' => null, 'notes' => null])
        ->and($page->noteFormData)->toBe([
            'note_type' => null,
            'status' => NoteStatus::DRAFT->value,
            'subject' => null,
            'content' => null,
        ])
        ->and($page->dischargeData)->toBe([
            'discharge_notes' => null,
            'discharge_disposition' => DischargeDisposition::COMPLETED->value,
            'transfer_destination' => null,
        ])
        ->and($page->adtFormData)->toBe(expectedAdtFormDefaults())
        ->and($page->consultationChiefComplaint)->toBe('')
        ->and($page->encounterFormData)->toBe([
            'type' => EncounterType::OUTPATIENT->value,
            'coverage_type' => null,
            'claim_check_code' => null,
            'chief_complaint' => null,
        ])
        ->and($page->vitalsData)->toHaveKey('systolic_bp')
        ->and($page->allergyData)->toHaveKey('allergen_name')
        ->and($page->serviceRequestData)->toHaveKey('priority')
        ->and($page->diagnosisFormData['diagnoses'][0])->toHaveKey('description')
        ->and($page->medicationData['items'][0])->toHaveKey('service_id')
        ->and($page->labResultData)->toBe([]);
});
