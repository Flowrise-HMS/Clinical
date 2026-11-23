<?php

use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

if (! function_exists('clinicalApiPatient')) {
    function clinicalApiPatient(Branch $branch): Patient
    {
        return Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $branch->id]));
    }

    function clinicalApiEncounter(Branch $branch, array $attributes = []): Encounter
    {
        $patient = clinicalApiPatient($branch);

        return Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::OUTPATIENT,
            'status' => EncounterStatus::PLANNED,
            ...$attributes,
        ]);
    }

    function clinicalApiRequestItem(Branch $branch): RequestItem
    {
        $encounter = clinicalApiEncounter($branch);
        $request = ServiceRequest::factory()->create([
            'patient_id' => $encounter->patient_id,
            'encounter_id' => $encounter->id,
            'branch_id' => $branch->id,
        ]);

        return RequestItem::factory()->create(['service_request_id' => $request->id]);
    }
}
