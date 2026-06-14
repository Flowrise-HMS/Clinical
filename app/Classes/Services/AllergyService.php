<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Models\Allergy;
use Modules\Patient\Models\Patient;

class AllergyService
{
    public function record(Patient $patient, array $data): Allergy
    {
        return DB::transaction(function () use ($patient, $data) {
            return Allergy::create([
                'patient_id' => $patient->id,
                'allergen' => $data['allergen_name'],
                'allergen_type' => isset($data['allergen_type'])
                    ? ($data['allergen_type'])
                    : null,
                'reaction' => $data['reaction'] ?? null,
                'severity' => isset($data['severity'])
                    ? ($data['severity'])
                    : null,
                'onset_type' => isset($data['onset_type'])
                    ? ($data['onset_type'])
                    : null,
                'onset_date' => $data['onset_date'] ?? null,
                'verification_status' => isset($data['verification_status'])
                    ? ($data['verification_status'])
                    : AllergyVerificationStatus::UNVERIFIED,
                'verified_by' => $data['verified_by'] ?? null,
                'verified_at' => $data['verified_at'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function getActiveForPatient(string $patientId): \Illuminate\Support\Collection
    {
        return Allergy::query()
            ->active()
            ->forPatient($patientId)
            ->get();
    }
}
