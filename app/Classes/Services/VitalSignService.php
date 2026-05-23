<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Classes\Services\BranchService;
use Modules\Patient\Models\Patient;

class VitalSignService
{
    public function __construct(
        protected BranchService $branchService
    ) {}

    public function record(
        Patient $patient,
        array $vitalData,
        ?string $encounterId = null,
        ?VitalSignType $type = null,
        ?int $recordedBy = null
    ): VitalSign {
        return DB::transaction(function () use ($patient, $vitalData, $type, $recordedBy) {
            $data = [
                'patient_id' => $patient->id,
                'branch_id' => $patient->branch_id,
                'recorded_by' => $recordedBy ?? auth()->id(),
                'recorded_at' => $vitalData['recorded_at'] ?? now(),
                'type' => $type ?? VitalSignType::ROUTINE,
            ];

            $optionalFields = [
                'encounter_id', 'position', 'measurement_location',
                'systolic_bp', 'diastolic_bp', 'heart_rate',
                'respiratory_rate', 'temperature', 'spo2',
                'weight', 'height', 'pain_level',
                'gcs_eye', 'gcs_verbal', 'gcs_motor', 'notes',
            ];

            foreach ($optionalFields as $field) {
                if (isset($vitalData[$field]) && $vitalData[$field] !== '') {
                    $data[$field] = $vitalData[$field];
                }
            }

            return VitalSign::create($data);
        });
    }

    public function getLatest(Patient $patient): ?VitalSign
    {
        return VitalSign::where('patient_id', $patient->id)
            ->latest('recorded_at')
            ->first();
    }

    public function getHistory(Patient $patient, int $limit = 10): Collection
    {
        return VitalSign::where('patient_id', $patient->id)
            ->with(['recordedBy'])
            ->latest('recorded_at')
            ->limit($limit)
            ->get();
    }

    public function getByEncounter(string $encounterId): Collection
    {
        return VitalSign::where('encounter_id', $encounterId)
            ->with(['recordedBy'])
            ->orderBy('recorded_at')
            ->get();
    }

    public function getAbnormalVitals(?string $branchId = null): Collection
    {
        $query = VitalSign::query()
            ->with(['patient', 'recordedBy'])
            ->where(function ($q) {
                $q->where('systolic_bp', '>=', 140)
                    ->orWhere('diastolic_bp', '>=', 90)
                    ->orWhereColumn('spo2', '<', 95)
                    ->orWhere('respiratory_rate', '>', 20)
                    ->orWhere('heart_rate', '>', 100);
            })
            ->where('recorded_at', '>=', now()->subHours(24));

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('recorded_at', 'desc')->get();
    }

    public function getTrend(Patient $patient, string $vitalType, int $days = 7): Collection
    {
        $validTypes = ['systolic_bp', 'diastolic_bp', 'heart_rate', 'respiratory_rate', 'temperature', 'spo2'];

        if (! in_array($vitalType, $validTypes)) {
            throw new \InvalidArgumentException("Invalid vital type: {$vitalType}");
        }

        return VitalSign::where('patient_id', $patient->id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->whereNotNull($vitalType)
            ->orderBy('recorded_at')
            ->get(['id', 'recorded_at', $vitalType]);
    }
}
