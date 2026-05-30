<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;

class MedicationAdministrationService
{
    public function administerBatch(array $administrations, ?string $notes = null, ?User $user = null): array
    {
        $user = $user ?? Auth::user();
        $errors = [];
        $created = [];

        foreach ($administrations as $admin) {
            if (! ($admin['selected'] ?? false)) {
                continue;
            }

            $item = RequestItem::find($admin['request_item_id']);
            if (! $item) {
                continue;
            }

            try {
                $this->administer($item, $admin, $notes, $user);
                $created[] = $item->service?->name ?? 'Unknown';
            } catch (\InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    public function administer(RequestItem $item, array $data, ?string $notes = null, ?User $user = null): MedicationAdministration
    {
        $user = $user ?? Auth::user();
        $detail = $item->prescriptionDetail;

        if ($detail && $detail->total_administrations) {
            $given = $item->medicationAdministrations()->sum('quantity_given');
            $remaining = $detail->total_administrations - $given;
            if (($data['quantity_given'] ?? 1) > $remaining) {
                throw new \InvalidArgumentException(
                    "{$item->service?->name}: exceeds remaining {$remaining} dose(s)"
                );
            }
        }

        $startedAt = $data['started_at'] ?? now()->format('H:i');
        $endedAt = $data['ended_at'] ?? $startedAt;

        if ($endedAt && $startedAt && $endedAt < $startedAt) {
            throw new \InvalidArgumentException(
                "{$item->service?->name}: ended time cannot be before started time"
            );
        }

        $administration = DB::transaction(function () use ($item, $startedAt, $endedAt, $data, $notes, $user) {
            $administration = MedicationAdministration::create([
                'request_item_id' => $item->id,
                'administered_by' => $user->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'quantity_given' => $data['quantity_given'] ?? 1,
                'dose_unit_id' => $data['dose_unit_id'] ?? $item->prescriptionDetail?->dose_unit_id,
                'status' => MedicationAdministrationStatus::GIVEN,
                'notes' => $notes,
            ]);

            if ($item->isPending()) {
                $item->markAsInProgress();
            }

            $totalGiven = $item->medicationAdministrations()->sum('quantity_given');
            $detail = $item->prescriptionDetail;
            if ($detail && $detail->total_administrations && $totalGiven >= $detail->total_administrations) {
                $item->markAsFulfilled($user->id);
            }

            return $administration;
        });

        return $administration;
    }

    public function getPendingItems(?string $patientId = null): Collection
    {
        return RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('prescriptionDetail')
            ->when($patientId, fn ($q) => $q->whereHas('serviceRequest', fn ($q) => $q->where('patient_id', $patientId)))
            ->with(['service', 'prescriptionDetail', 'medicationAdministrations', 'serviceRequest.patient'])
            ->get();
    }

    public function getRemainingDoses(RequestItem $item): int|string
    {
        $detail = $item->prescriptionDetail;
        if (! $detail || ! $detail->total_administrations) {
            return '∞';
        }

        $given = $item->medicationAdministrations()->sum('quantity_given');
        return max(0, $detail->total_administrations - $given);
    }
}
