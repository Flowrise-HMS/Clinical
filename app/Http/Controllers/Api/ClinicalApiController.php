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
}
