<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;

class AdtService
{
    public function __construct(
        protected EncounterService $encounterService,
        protected BedAssignmentService $bedAssignmentService,
    ) {}

    public function admit(
        Patient $patient,
        string $bedId,
        ?string $departmentId = null,
        ?string $chiefComplaint = null,
        ?EncounterPriority $priority = null,
        ?int $actedBy = null,
        ?string $notes = null,
    ): Encounter {
        return DB::transaction(function () use ($patient, $bedId, $departmentId, $chiefComplaint, $priority, $actedBy, $notes) {
            $actedBy ??= Auth::id();
            $this->assertBedAvailable($bedId);

            $encounter = $this->resolveEncounterForAdmission($patient, $chiefComplaint, $priority, $actedBy);

            if ($departmentId) {
                $encounter->update(['department_id' => $departmentId]);
            }

            $from = $this->snapshot($encounter);

            $encounter = $this->bedAssignmentService->assignBed($encounter, $bedId, $actedBy);
            $encounter = $this->syncLocationFromBed($encounter, $bedId);

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::Admitted,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
            );

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    public function transferInternal(
        Encounter $encounter,
        string $toBedId,
        ?string $toDepartmentId = null,
        ?string $notes = null,
        ?int $actedBy = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $toBedId, $toDepartmentId, $notes, $actedBy) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();

            if ($encounter->isCompleted()) {
                throw new \InvalidArgumentException(__('Cannot transfer a completed encounter.'));
            }

            if (! $encounter->status?->isActive() && $encounter->status !== EncounterStatus::PLANNED) {
                throw new \InvalidArgumentException(__('Encounter is not active for internal transfer.'));
            }

            $this->assertBedAvailable($toBedId, $encounter->id);

            $from = $this->snapshot($encounter);

            $encounter = $this->bedAssignmentService->assignBed($encounter, $toBedId, $actedBy);
            $encounter = $this->syncLocationFromBed($encounter, $toBedId);

            if ($toDepartmentId) {
                $encounter->update(['department_id' => $toDepartmentId]);
                $encounter = $encounter->fresh();
            }

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredInternal,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: AdtDestinationType::InternalUnit,
            );

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    public function transferOut(
        Encounter $encounter,
        AdtDestinationType $destinationType,
        ?string $destinationLabel = null,
        ?string $destinationBranchId = null,
        ?string $notes = null,
        ?int $actedBy = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $destinationType, $destinationLabel, $destinationBranchId, $notes, $actedBy) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();

            if ($destinationType === AdtDestinationType::Branch && blank($destinationBranchId)) {
                throw new \InvalidArgumentException(__('A destination branch is required.'));
            }

            if ($destinationType === AdtDestinationType::ExternalFacility && blank($destinationLabel)) {
                throw new \InvalidArgumentException(__('A destination facility name is required.'));
            }

            $label = $destinationLabel;
            if ($destinationType === AdtDestinationType::Branch && filled($destinationBranchId)) {
                $label = $label ?: (string) \Modules\Core\Models\Branch::query()->find($destinationBranchId)?->name;
            }

            $from = $this->snapshot($encounter);

            $encounter = $this->encounterService->discharge(
                $encounter,
                DischargeDisposition::TRANSFERRED,
                $label,
                $actedBy,
            );

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredOut,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: $destinationType,
                destinationBranchId: $destinationBranchId,
                destinationLabel: $label,
            );

            return $encounter->fresh(['patient', 'branch']);
        });
    }

    public function transferIn(
        Patient $patient,
        string $bedId,
        ?string $sourceLabel = null,
        ?string $fromBranchId = null,
        ?string $departmentId = null,
        ?string $chiefComplaint = null,
        ?EncounterPriority $priority = null,
        ?int $actedBy = null,
        ?string $notes = null,
    ): Encounter {
        return DB::transaction(function () use ($patient, $bedId, $sourceLabel, $fromBranchId, $departmentId, $chiefComplaint, $priority, $actedBy, $notes) {
            $actedBy ??= Auth::id();
            $this->assertNoOpenEncounter($patient);
            $this->assertBedAvailable($bedId);

            $encounter = $this->encounterService->createForPatient(
                patient: $patient,
                type: EncounterType::INPATIENT,
                chiefComplaint: $chiefComplaint,
                priority: $priority ?? EncounterPriority::ROUTINE,
                departmentId: $departmentId,
                createdBy: $actedBy,
            );

            $metadata = array_merge($encounter->metadata ?? [], [
                'transfer_in' => [
                    'source_label' => $sourceLabel,
                    'from_branch_id' => $fromBranchId,
                    'received_at' => now()->toIso8601String(),
                ],
            ]);
            $encounter->update(['metadata' => $metadata]);

            $from = $this->snapshot($encounter);

            $encounter = $this->bedAssignmentService->assignBed($encounter, $bedId, $actedBy);
            $encounter = $this->syncLocationFromBed($encounter, $bedId);

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredIn,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: filled($fromBranchId) ? AdtDestinationType::Branch : AdtDestinationType::ExternalFacility,
                destinationBranchId: $fromBranchId,
                destinationLabel: $sourceLabel,
            );

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    public function discharge(
        Encounter $encounter,
        ?DischargeDisposition $disposition = null,
        ?string $transferDestination = null,
        ?string $notes = null,
        ?int $actedBy = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $disposition, $transferDestination, $notes, $actedBy) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $disposition ??= DischargeDisposition::COMPLETED;

            $encounter = $this->encounterService->discharge(
                $encounter,
                $disposition,
                $transferDestination,
                $actedBy,
            );

            if (filled($notes)) {
                $metadata = array_merge($encounter->metadata ?? [], ['discharge_notes' => $notes]);
                $encounter->forceFill(['metadata' => $metadata])->saveQuietly();
            }

            $this->logEvent(
                encounter: $encounter,
                type: $disposition === DischargeDisposition::TRANSFERRED
                    ? AdtEventType::TransferredOut
                    : AdtEventType::Discharged,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: $disposition === DischargeDisposition::TRANSFERRED
                    ? AdtDestinationType::ExternalFacility
                    : null,
                destinationLabel: $transferDestination,
            );

            return $encounter->fresh(['patient', 'branch']);
        });
    }

    public function assignBed(
        Encounter $encounter,
        string $bedId,
        ?int $actedBy = null,
        ?string $notes = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $bedId, $actedBy, $notes) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $wasPlanned = $encounter->canTransitionTo(EncounterStatus::ARRIVED);

            $encounter = $this->bedAssignmentService->assignBed($encounter, $bedId, $actedBy);
            $encounter = $this->syncLocationFromBed($encounter, $bedId);

            $this->logEvent(
                encounter: $encounter,
                type: $wasPlanned ? AdtEventType::Admitted : AdtEventType::BedAssigned,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
            );

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    protected function resolveEncounterForAdmission(
        Patient $patient,
        ?string $chiefComplaint,
        ?EncounterPriority $priority,
        ?int $actedBy,
    ): Encounter {
        $open = $patient->activeEncounter()->first();

        if ($open instanceof Encounter) {
            if ($open->type !== EncounterType::INPATIENT) {
                throw new \InvalidArgumentException(__('Patient already has an open non-inpatient encounter. Finish or cancel it before admitting.'));
            }

            return $open;
        }

        return $this->encounterService->createForPatient(
            patient: $patient,
            type: EncounterType::INPATIENT,
            chiefComplaint: $chiefComplaint,
            priority: $priority ?? EncounterPriority::ROUTINE,
            createdBy: $actedBy,
        );
    }

    protected function assertNoOpenEncounter(Patient $patient): void
    {
        if ($patient->activeEncounter()->exists()) {
            throw new \InvalidArgumentException(__('Patient already has an open encounter.'));
        }
    }

    protected function assertBedAvailable(string $bedId, ?string $exceptEncounterId = null): void
    {
        $query = Encounter::active()
            ->where('bed_id', $bedId);

        if ($exceptEncounterId) {
            $query->where('id', '!=', $exceptEncounterId);
        }

        if ($query->exists()) {
            throw new \RuntimeException(__('clinical::messages.bed_already_occupied'));
        }
    }

    protected function syncLocationFromBed(Encounter $encounter, string $bedId): Encounter
    {
        $bed = Location::query()->find($bedId);
        if ($bed?->parent_id) {
            $encounter->update([
                'bed_id' => $bedId,
                'location_id' => $bed->parent_id,
            ]);
        }

        return $encounter->fresh();
    }

    /**
     * @return array{bed_id: ?string, location_id: ?string, department_id: ?string}
     */
    protected function snapshot(Encounter $encounter): array
    {
        return [
            'bed_id' => $encounter->bed_id,
            'location_id' => $encounter->location_id,
            'department_id' => $encounter->department_id,
        ];
    }

    /**
     * @param  array{bed_id: ?string, location_id: ?string, department_id: ?string}  $from
     */
    protected function logEvent(
        Encounter $encounter,
        AdtEventType $type,
        array $from,
        ?int $actedBy = null,
        ?string $notes = null,
        ?AdtDestinationType $destinationType = null,
        ?string $destinationBranchId = null,
        ?string $destinationLabel = null,
    ): EncounterLocationEvent {
        return EncounterLocationEvent::query()->create([
            'branch_id' => $encounter->branch_id,
            'encounter_id' => $encounter->id,
            'patient_id' => $encounter->patient_id,
            'event_type' => $type,
            'from_bed_id' => $from['bed_id'],
            'to_bed_id' => $encounter->bed_id,
            'from_location_id' => $from['location_id'],
            'to_location_id' => $encounter->location_id,
            'from_department_id' => $from['department_id'],
            'to_department_id' => $encounter->department_id,
            'destination_type' => $destinationType,
            'destination_branch_id' => $destinationBranchId,
            'destination_label' => $destinationLabel,
            'notes' => $notes,
            'acted_by' => $actedBy ?? Auth::id(),
            'occurred_at' => now(),
        ]);
    }
}
