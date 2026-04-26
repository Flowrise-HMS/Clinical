<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterParticipant;
use Modules\Core\Classes\Services\BranchService;
use Modules\Patient\Models\Patient;

class EncounterService
{
    public function __construct(
        protected BranchService $branchService
    ) {}

    public function createForPatient(
        Patient $patient,
        EncounterType $type,
        ?string $chiefComplaint = null,
        ?EncounterPriority $priority = null,
        ?string $locationId = null,
        ?string $departmentId = null,
        ?int $createdBy = null
    ): Encounter {
        return DB::transaction(function () use ($patient, $type, $chiefComplaint, $priority, $locationId, $departmentId, $createdBy) {
            return Encounter::create([
                'patient_id' => $patient->id,
                'branch_id' => $patient->branch_id,
                'type' => $type,
                'status' => EncounterStatus::PLANNED,
                'priority' => $priority ?? EncounterPriority::default(),
                'chief_complaint' => $chiefComplaint,
                'location_id' => $locationId,
                'department_id' => $departmentId,
                'created_by' => $createdBy ?? auth()->id(),
            ]);
        });
    }

    public function createForGuest(
        string $guestName,
        string $guestPhone,
        EncounterType $type,
        ?string $guestEmail = null,
        ?EncounterPriority $priority = null,
        ?string $branchId = null,
        ?int $createdBy = null
    ): Encounter {
        return Encounter::create([
            'patient_id' => null,
            'branch_id' => $branchId ?? $this->branchService->getDefaultBranchId(),
            'type' => $type,
            'status' => EncounterStatus::PLANNED,
            'priority' => $priority ?? EncounterPriority::default(),
            'guest_name' => $guestName,
            'guest_phone' => $guestPhone,
            'guest_email' => $guestEmail,
            'created_by' => $createdBy ?? auth()->id(),
        ]);
    }

    public function admitPatient(
        Encounter $encounter,
        ?int $admittedBy = null,
        ?string $bedId = null
    ): Encounter {
        if (! $encounter->canTransitionTo(EncounterStatus::ARRIVED)) {
            throw new \InvalidArgumentException('Cannot admit patient in current status');
        }

        $updateData = [
            'status' => EncounterStatus::ARRIVED,
            'admitted_by' => $admittedBy ?? auth()->id(),
            'admitted_at' => now(),
        ];

        if ($bedId && $encounter->type === EncounterType::INPATIENT) {
            $updateData['bed_id'] = $bedId;
        }

        $encounter->update($updateData);

        return $encounter->fresh();
    }

    public function triage(
        Encounter $encounter,
        EncounterPriority $priority,
        ?string $locationId = null,
        ?string $departmentId = null
    ): Encounter {
        if (! $encounter->canTransitionTo(EncounterStatus::TRIAGED)) {
            throw new \InvalidArgumentException('Cannot triage in current status');
        }

        $updateData = ['status' => EncounterStatus::TRIAGED];

        if ($priority !== $encounter->priority) {
            $updateData['priority'] = $priority;
        }

        if ($locationId) {
            $updateData['location_id'] = $locationId;
        }

        if ($departmentId) {
            $updateData['department_id'] = $departmentId;
        }

        $encounter->update($updateData);

        return $encounter->fresh();
    }

    public function startEncounter(Encounter $encounter): Encounter
    {
        if (! $encounter->canTransitionTo(EncounterStatus::IN_PROGRESS)) {
            throw new \InvalidArgumentException('Cannot start encounter in current status');
        }

        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        return $encounter->fresh();
    }

    public function putOnLeave(Encounter $encounter, ?string $reason = null): Encounter
    {
        if (! $encounter->canTransitionTo(EncounterStatus::ON_LEAVE)) {
            throw new \InvalidArgumentException('Cannot put on leave in current status');
        }

        $encounter->update([
            'status' => EncounterStatus::ON_LEAVE,
            'metadata' => array_merge($encounter->metadata ?? [], ['leave_reason' => $reason]),
        ]);

        return $encounter->fresh();
    }

    public function returnFromLeave(Encounter $encounter): Encounter
    {
        if ($encounter->status !== EncounterStatus::ON_LEAVE) {
            throw new \InvalidArgumentException('Encounter is not on leave');
        }

        $encounter->update(['status' => EncounterStatus::IN_PROGRESS]);

        return $encounter->fresh();
    }

    public function discharge(
        Encounter $encounter,
        ?DischargeDisposition $disposition = null,
        ?string $transferDestination = null,
        ?int $dischargedBy = null
    ): Encounter {
        if (! $encounter->canTransitionTo(EncounterStatus::FINISHED)) {
            throw new \InvalidArgumentException('Cannot discharge in current status');
        }

        $updateData = [
            'status' => EncounterStatus::FINISHED,
            'discharged_by' => $dischargedBy ?? auth()->id(),
            'discharged_at' => now(),
            'discharge_disposition' => $disposition ?? DischargeDisposition::COMPLETED,
        ];

        if ($transferDestination) {
            $updateData['transfer_destination'] = $transferDestination;
        }

        $encounter->update($updateData);

        $encounter->participants()
            ->where('status', ParticipantStatus::ACTIVE)
            ->update([
                'status' => ParticipantStatus::COMPLETED,
                'left_at' => now(),
            ]);

        return $encounter->fresh();
    }

    public function cancelEncounter(Encounter $encounter, ?string $reason = null): Encounter
    {
        if ($encounter->isCompleted()) {
            throw new \InvalidArgumentException('Cannot cancel completed encounter');
        }

        $encounter->update([
            'status' => EncounterStatus::CANCELLED,
            'metadata' => array_merge($encounter->metadata ?? [], ['cancel_reason' => $reason]),
        ]);

        $encounter->participants()
            ->where('status', ParticipantStatus::ACTIVE)
            ->update([
                'status' => ParticipantStatus::COMPLETED,
                'left_at' => now(),
            ]);

        return $encounter->fresh();
    }

    public function addParticipant(
        Encounter $encounter,
        int $userId,
        ParticipantRole $role,
        ?int $joinedBy = null
    ): EncounterParticipant {
        $existingParticipant = $encounter->participants()
            ->where('user_id', $userId)
            ->where('status', ParticipantStatus::ACTIVE)
            ->first();

        if ($existingParticipant) {
            throw new \InvalidArgumentException('User is already an active participant');
        }

        return $encounter->participants()->create([
            'user_id' => $userId,
            'role' => $role,
            'status' => ParticipantStatus::ACTIVE,
            'joined_at' => now(),
            'notes' => 'Added by: '.($joinedBy ?? auth()->id()),
        ]);
    }

    public function removeParticipant(Encounter $encounter, int $userId): void
    {
        $encounter->participants()
            ->where('user_id', $userId)
            ->where('status', ParticipantStatus::ACTIVE)
            ->update([
                'status' => ParticipantStatus::COMPLETED,
                'left_at' => now(),
            ]);
    }

    public function handoverParticipant(
        Encounter $encounter,
        int $fromUserId,
        int $toUserId,
        ParticipantRole $newRole
    ): void {
        DB::transaction(function () use ($encounter, $fromUserId, $toUserId, $newRole) {
            $this->removeParticipant($encounter, $fromUserId);
            $this->addParticipant($encounter, $toUserId, $newRole);
        });
    }

    public function getActiveEncounters(?string $branchId = null): Collection
    {
        $query = Encounter::active()
            ->with(['patient', 'participants.user', 'location']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->orderBy('priority')->orderBy('created_at')->get();
    }

    public function getEncounterByNumber(string $encounterNumber): ?Encounter
    {
        return Encounter::where('encounter_number', $encounterNumber)
            ->with(['patient', 'participants.user', 'serviceRequests.items.service'])
            ->first();
    }

    public function getPatientEncounterHistory(Patient $patient): Collection
    {
        return Encounter::where('patient_id', $patient->id)
            ->with(['participants.user', 'serviceRequests.items.service'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function getActiveParticipants(Encounter $encounter): Collection
    {
        return $encounter->participants()
            ->where('status', ParticipantStatus::ACTIVE)
            ->with('user')
            ->get();
    }
}
