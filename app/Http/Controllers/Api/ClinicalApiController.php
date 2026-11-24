<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Modules\Clinical\Models\Encounter;
use Modules\Core\Http\Controllers\Api\ResourceApiController;
use Modules\Patient\Models\Patient;

/**
 * Shared helpers for Clinical API resources. Patients and encounters are
 * resolved through their branch-scoped queries, so ids from another branch 404.
 */
abstract class ClinicalApiController extends ResourceApiController
{
    protected function patient(string $id): Patient
    {
        return Patient::query()->findOrFail($id);
    }

    protected function encounter(string $id): Encounter
    {
        return Encounter::query()->findOrFail($id);
    }

    /**
     * The encounter id, provided the encounter belongs to the patient.
     */
    protected function encounterIdFor(Patient $patient, ?string $encounterId): ?string
    {
        if ($encounterId === null) {
            return null;
        }

        $encounter = $this->encounter($encounterId);

        if ((string) $encounter->patient_id !== (string) $patient->getKey()) {
            throw new \InvalidArgumentException('The encounter belongs to another patient.');
        }

        return (string) $encounter->getKey();
    }

    /**
     * Authorisation for records the panel guards through their encounter
     * (admission requests, triage): reading follows encounter reads, anything
     * else needs to be able to update the encounter.
     */
    protected function authorizeThroughEncounter(string $ability, ?Encounter $encounter = null): void
    {
        match (true) {
            $ability === 'viewAny' => $this->authorizeApi('viewAny', Encounter::class),
            $encounter === null => $this->requirePermission('Update Encounter'),
            $ability === 'view' => $this->authorizeApi('view', $encounter),
            default => $this->authorizeApi('update', $encounter),
        };
    }
}
