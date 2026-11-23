<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Task;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

require_once __DIR__.'/ClinicalApiTestHelpers.php';

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Api');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Billing', 'Pharmacy', 'Clinical']);
    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

    foreach (['Encounter', 'ClinicalNote', 'ServiceRequest', 'Task', 'AdmissionRequest'] as $subject) {
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            Permission::findOrCreate("{$action} {$subject}", 'web');
            $this->user->givePermissionTo("{$action} {$subject}");
        }
    }
    Permission::findOrCreate('sign_discharge_summary', 'web');
    $this->user->givePermissionTo('sign_discharge_summary');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken($this->user->createToken('t', ['clinical:write'])->plainTextToken);
});

it('moves an outpatient encounter through arrive, triage, start and complete', function (): void {
    foreach (['ViewAny', 'View', 'Create'] as $action) {
        Permission::findOrCreate("{$action} TriageAssessment", 'web');
        $this->user->givePermissionTo("{$action} TriageAssessment");
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $encounter = clinicalApiEncounter($this->branch);

    $this->postJson("/api/v1/encounters/{$encounter->id}/arrive")->assertOk()->assertJsonPath('data.status', EncounterStatus::ARRIVED->value);
    $this->postJson('/api/v1/triage-assessments', ['encounter_id' => $encounter->id, 'mobility' => 'walking', 'avpu' => 'alert', 'heart_rate' => 80])->assertCreated();
    $this->postJson("/api/v1/encounters/{$encounter->id}/start")->assertOk()->assertJsonPath('data.status', EncounterStatus::IN_PROGRESS->value);
    $this->postJson("/api/v1/encounters/{$encounter->id}/complete", ['notes' => 'Done'])->assertOk();

    expect($encounter->fresh()->status->isActive())->toBeFalse();
});

it('rejects completing an encounter that is not active with 422', function (): void {
    $encounter = clinicalApiEncounter($this->branch, ['status' => EncounterStatus::CANCELLED]);

    $this->postJson("/api/v1/encounters/{$encounter->id}/complete")->assertUnprocessable()->assertJsonPath('success', false);
});

it('signs a note and then refuses edits', function (): void {
    $encounter = clinicalApiEncounter($this->branch);
    $note = ClinicalNote::factory()->create(['patient_id' => $encounter->patient_id, 'encounter_id' => $encounter->id, 'is_signed' => false]);

    $this->postJson("/api/v1/clinical-notes/{$note->id}/sign")->assertOk()->assertJsonPath('data.is_signed', true);
    $this->patchJson("/api/v1/clinical-notes/{$note->id}", ['content' => 'Changed'])->assertUnprocessable();
});

it('creates a service request with items and cancels it', function (): void {
    $patient = clinicalApiPatient($this->branch);
    $service = Service::factory()->create();

    $id = $this->postJson('/api/v1/service-requests', [
        'patient_id' => $patient->id,
        'items' => [['service_id' => $service->id, 'quantity' => 2]],
    ])->assertCreated()
        ->assertJsonPath('data.items.0.service_id', $service->id)
        ->assertJsonPath('data.items.0.quantity', 2)
        ->json('data.id');

    $this->postJson("/api/v1/service-requests/{$id}/cancel", ['reason' => 'Duplicate'])
        ->assertOk()
        ->assertJsonPath('data.status', RequestStatus::CANCELLED->value);
});

it('refuses a patient from another branch', function (): void {
    $foreign = clinicalApiPatient(Branch::factory()->create());

    $this->postJson('/api/v1/encounters', ['patient_id' => $foreign->id, 'type' => EncounterType::OUTPATIENT->value])->assertNotFound();
});

it('starts and completes a task', function (): void {
    $task = Task::factory()->create(['request_item_id' => clinicalApiRequestItem($this->branch)->id]);

    $this->postJson("/api/v1/tasks/{$task->id}/start")->assertOk()->assertJsonPath('data.status', TaskStatus::IN_PROGRESS->value);
    $this->postJson("/api/v1/tasks/{$task->id}/complete", ['notes' => 'Sample collected'])->assertOk()->assertJsonPath('data.status', TaskStatus::COMPLETED->value);
});

it('rejects an admission request', function (): void {
    $admission = AdmissionRequest::factory()->create(['encounter_id' => clinicalApiEncounter($this->branch)->id]);

    $this->postJson("/api/v1/admission-requests/{$admission->id}/reject", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson("/api/v1/admission-requests/{$admission->id}/reject", ['reason' => 'No bed'])
        ->assertOk()
        ->assertJsonPath('data.status', AdmissionRequestStatus::Rejected->value);
});

it('drafts, edits and signs a discharge summary', function (): void {
    $encounter = clinicalApiEncounter($this->branch, ['type' => EncounterType::INPATIENT, 'status' => EncounterStatus::IN_PROGRESS]);

    $id = $this->postJson('/api/v1/discharge-summaries', ['encounter_id' => $encounter->id])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/discharge-summaries/{$id}", ['hospital_course' => 'Uneventful recovery.'])->assertOk()->assertJsonPath('data.hospital_course', 'Uneventful recovery.');
    $this->postJson("/api/v1/discharge-summaries/{$id}/sign")->assertOk()->assertJsonPath('data.signed_by', $this->user->id);
    $this->patchJson("/api/v1/discharge-summaries/{$id}", ['diet' => 'Normal'])->assertUnprocessable();
});
