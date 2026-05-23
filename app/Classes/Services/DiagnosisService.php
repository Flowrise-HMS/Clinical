<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Patient\Models\Patient;

class DiagnosisService
{
    public function record(
        Patient $patient,
        array $diagnoses,
        string $encounterId,
        ?int $orderedBy = null,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($patient, $diagnoses, $encounterId, $orderedBy, $notes) {
            foreach ($diagnoses as $i => $dx) {
                $icdCode = null;
                $diagnosisCodeId = null;
                $description = null;

                if (! empty($dx['id'])) {
                    $code = DiagnosisCode::find($dx['id']);
                    if ($code) {
                        $diagnosisCodeId = $code->id;
                        $icdCode = $code->code;
                        $description = $dx['label'] ?? $code->description;
                    }
                }

                $description ??= $dx['label'] ?? null;

                if (! $description) {
                    continue;
                }

                EncounterDiagnosis::create([
                    'encounter_id' => $encounterId,
                    'patient_id' => $patient->id,
                    'diagnosis_code_id' => $diagnosisCodeId,
                    'icd_code' => $icdCode,
                    'description' => $description,
                    'notes' => $notes,
                    'type' => $i === 0 ? 'primary' : 'secondary',
                    'ordered_by' => $orderedBy ?? auth()->id(),
                ]);
            }
        });
    }

    public function getForEncounter(string $encounterId): array
    {
        $records = EncounterDiagnosis::where('encounter_id', $encounterId)
            ->where('is_active', true)
            ->with('diagnosisCode')
            ->get();

        return [
            'diagnoses' => $records->map(fn ($dx) => [
                'id' => $dx->diagnosis_code_id,
                'code' => $dx->icd_code,
                'label' => $dx->description,
            ])->toArray(),
            'notes' => $records->first()?->notes ?? '',
        ];
    }
}
