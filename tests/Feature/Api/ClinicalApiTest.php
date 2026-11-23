<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Allergy;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\Task;
use Modules\Clinical\Models\TriageAssessment;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Tests\Support\ApiResourceTestSuite;
use Tests\TestCase;

require_once __DIR__.'/ClinicalApiTestHelpers.php';

uses(TestCase::class, DatabaseTransactions::class);

$modules = ['Core', 'Patient', 'Staff', 'Billing', 'Pharmacy', 'Clinical'];

describe('encounters', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'encounters',
        'subject' => 'Encounter',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => clinicalApiEncounter($branch, $attributes),
        'store' => fn (TestCase $test, Branch $branch): array => [
            'patient_id' => clinicalApiPatient($branch)->id,
            'type' => EncounterType::OUTPATIENT->value,
            'chief_complaint' => 'Headache',
        ],
        'invalid' => [['type' => 'not-a-type'], 'type'],
        'update' => fn (TestCase $test, $record): array => ['chief_complaint' => 'Fever'],
    ]);
});

describe('vital signs', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'vital-signs',
        'subject' => 'VitalSign',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch);

            return VitalSign::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'branch_id' => $branch->id, ...$attributes]);
        },
        'store' => fn (TestCase $test, Branch $branch): array => [
            'patient_id' => clinicalApiPatient($branch)->id,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'temperature' => 36.8,
        ],
        'invalid' => [['temperature' => 80], 'temperature'],
    ]);
});

describe('allergies', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'allergies',
        'subject' => 'Allergy',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Allergy::factory()->create(['patient_id' => clinicalApiPatient($branch)->id, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => ['patient_id' => clinicalApiPatient($branch)->id, 'allergen' => 'Penicillin', 'reaction' => 'Rash', 'severity' => 'moderate'],
        'invalid' => [['allergen' => ''], 'allergen'],
        'update' => fn (TestCase $test, $record): array => ['reaction' => 'Hives'],
        'destroy' => true,
    ]);
});

describe('clinical notes', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'clinical-notes',
        'subject' => 'ClinicalNote',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch);

            return ClinicalNote::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'is_signed' => false, ...$attributes]);
        },
        'store' => fn (TestCase $test, Branch $branch): array => ['patient_id' => clinicalApiPatient($branch)->id, 'note_type' => 'progress', 'subject' => 'Review', 'content' => 'Doing well.'],
        'invalid' => [['content' => ''], 'content'],
        'update' => fn (TestCase $test, $record): array => ['content' => 'Updated note.'],
    ]);
});

describe('encounter diagnoses', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'encounter-diagnoses',
        'subject' => 'EncounterDiagnosis',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch);

            return EncounterDiagnosis::factory()->create(['encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id, ...$attributes]);
        },
        'store' => fn (TestCase $test, Branch $branch): array => ['encounter_id' => clinicalApiEncounter($branch)->id, 'description' => 'Malaria', 'icd10_code' => 'B54'],
        'invalid' => [['encounter_id' => 'not-a-uuid'], 'encounter_id'],
        'update' => fn (TestCase $test, $record): array => ['notes' => 'Confirmed by RDT'],
        'destroy' => false,
    ]);
});

describe('service requests', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'service-requests',
        'subject' => 'ServiceRequest',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch);

            return ServiceRequest::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'branch_id' => $branch->id, ...$attributes]);
        },
        'store' => fn (TestCase $test, Branch $branch): array => [
            'patient_id' => clinicalApiPatient($branch)->id,
            'items' => [['service_id' => Service::factory()->create()->id, 'quantity' => 1]],
        ],
        'invalid' => [['items' => []], 'items'],
        'update' => fn (TestCase $test, $record): array => ['notes' => 'Urgent please'],
    ]);
});

describe('tasks', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'tasks',
        'subject' => 'Task',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Task::factory()->create(['request_item_id' => clinicalApiRequestItem($branch)->id, ...$attributes]),
    ]);
});

describe('care plans', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'care-plans',
        'subject' => 'CarePlan',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch);

            return CarePlan::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'branch_id' => $branch->id, ...$attributes]);
        },
        'update' => fn (TestCase $test, $record): array => ['title' => 'Post-op care'],
    ]);
});

describe('medication administrations', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'medication-administrations',
        'subject' => 'MedicationAdministration',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => MedicationAdministration::factory()->create(['request_item_id' => clinicalApiRequestItem($branch)->id, ...$attributes]),
    ]);
});

describe('admission requests', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'admission-requests',
        'subject' => 'AdmissionRequest',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => AdmissionRequest::factory()->create(['encounter_id' => clinicalApiEncounter($branch)->id, ...$attributes]),
    ]);
});

describe('discharge summaries', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'discharge-summaries',
        // Access follows encounter permissions (see DischargeSummaryPolicy).
        'subject' => 'Encounter',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => function (TestCase $test, Branch $branch, array $attributes = []) {
            $encounter = clinicalApiEncounter($branch, ['type' => EncounterType::INPATIENT]);

            return DischargeSummary::factory()->create(['encounter_id' => $encounter->id, 'patient_id' => $encounter->patient_id, 'branch_id' => $branch->id, ...$attributes]);
        },
    ]);
});

describe('triage assessments', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'triage-assessments',
        'subject' => 'TriageAssessment',
        'scope' => 'clinical',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => TriageAssessment::factory()->create(['encounter_id' => clinicalApiEncounter($branch)->id, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => [
            'encounter_id' => clinicalApiEncounter($branch)->id,
            'mobility' => 'walking',
            'avpu' => 'alert',
            'heart_rate' => 88,
            'respiratory_rate' => 18,
            'temperature' => 37.1,
            'systolic_bp' => 118,
        ],
        'invalid' => [['override_category' => 'red'], 'override_reason'],
    ]);
});
