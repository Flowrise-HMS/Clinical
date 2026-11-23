<?php

use Modules\Clinical\Http\Controllers\Api\AdmissionRequestController;
use Modules\Clinical\Http\Controllers\Api\AllergyController;
use Modules\Clinical\Http\Controllers\Api\CarePlanController;
use Modules\Clinical\Http\Controllers\Api\ClinicalNoteController;
use Modules\Clinical\Http\Controllers\Api\DischargeSummaryController;
use Modules\Clinical\Http\Controllers\Api\EncounterController;
use Modules\Clinical\Http\Controllers\Api\EncounterDiagnosisController;
use Modules\Clinical\Http\Controllers\Api\MedicationAdministrationController;
use Modules\Clinical\Http\Controllers\Api\ServiceRequestController;
use Modules\Clinical\Http\Controllers\Api\TaskController;
use Modules\Clinical\Http\Controllers\Api\TriageAssessmentController;
use Modules\Clinical\Http\Controllers\Api\VitalSignController;
use Modules\Core\Services\ApiRouteRegistrar;

/*
| Clinical REST API (`/api/v1`, Sanctum + branch, token scope "clinical").
| Records are immutable where the clinical record requires it (vitals, MAR,
| triage); lifecycle changes go through action endpoints backed by services.
*/
ApiRouteRegistrar::register(scope: 'clinical', routes: function (): void {
    EncounterController::routes('encounters', ['index', 'show', 'store', 'update'], [
        'arrive' => 'arrive', 'start' => 'start', 'complete' => 'complete', 'cancel' => 'cancel',
    ]);
    VitalSignController::routes('vital-signs', ['index', 'show', 'store']);
    AllergyController::routes('allergies');
    ClinicalNoteController::routes('clinical-notes', ['index', 'show', 'store', 'update'], ['sign' => 'sign']);
    EncounterDiagnosisController::routes('encounter-diagnoses');
    ServiceRequestController::routes('service-requests', ['index', 'show', 'store', 'update'], ['submit' => 'submit', 'cancel' => 'cancel']);
    TaskController::routes('tasks', ['index', 'show'], ['start' => 'start', 'complete' => 'complete', 'cancel' => 'cancel']);
    CarePlanController::routes('care-plans', ['index', 'show', 'store', 'update'], [
        'activate' => 'activate', 'hold' => 'hold', 'complete' => 'complete', 'revoke' => 'revoke',
    ]);
    MedicationAdministrationController::routes('medication-administrations', ['index', 'show', 'store']);
    AdmissionRequestController::routes('admission-requests', ['index', 'show', 'store'], [
        'accept' => 'accept', 'reject' => 'reject', 'cancel' => 'cancel',
    ]);
    DischargeSummaryController::routes('discharge-summaries', ['index', 'show', 'store', 'update'], ['sign' => 'sign']);
    TriageAssessmentController::routes('triage-assessments', ['index', 'show', 'store']);
});
