<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Events\AdmissionAccepted;
use Modules\Clinical\Events\AdmissionRejected;
use Modules\Clinical\Events\AdmissionRequestCancelled;
use Modules\Clinical\Events\AdmissionRequested;
use Modules\Clinical\Events\PatientAdmitted;
use Modules\Clinical\Events\PatientDischarged;
use Modules\Clinical\Events\PatientTransferred;
use Modules\Clinical\Exceptions\DischargeBlockedException;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Core\Classes\Services\BedStatusService;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;

class AdtService
{
    public function __construct(
        protected EncounterService $encounterService,
        protected BedAssignmentService $bedAssignmentService,
        protected BedStatusService $bedStatusService,
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
            $this->assertBedAvailable($bedId, patientGender: $this->genderOf($patient));

            $encounter = $this->resolveEncounterForAdmission($patient, $chiefComplaint, $priority, $actedBy);

            if ($departmentId) {
                $encounter->update(['department_id' => $departmentId]);
            }

            $from = $this->snapshot($encounter);

            $encounter = $this->occupyBed($encounter, $bedId, $actedBy);
            $encounter = $this->encounterService->beginInpatientCare($encounter, $actedBy);
            $this->encounterService->ensureParticipant($encounter, $actedBy, ParticipantRole::ATTENDING, $actedBy);

            $event = $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::Admitted,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
            );

            $this->dispatchAfterCommit(new PatientAdmitted($encounter, $event, 'admit'));

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

            $this->assertBedAvailable($toBedId, $encounter->id, patientGender: $this->genderOf($encounter->patient));

            $from = $this->snapshot($encounter);

            $encounter = $this->occupyBed($encounter, $toBedId, $actedBy);
            $this->releaseBed($from['bed_id'], $encounter->id, $actedBy);

            if ($toDepartmentId) {
                $encounter->update(['department_id' => $toDepartmentId]);
                $encounter = $encounter->fresh();
            }

            $event = $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredInternal,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: AdtDestinationType::InternalUnit,
            );

            if ($encounter->isInpatient()) {
                $this->dispatchAfterCommit(new PatientTransferred($encounter, $event));
            }

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
                $label = $label ?: (string) Branch::query()->find($destinationBranchId)?->name;
            }

            $from = $this->snapshot($encounter);

            $encounter = $this->encounterService->discharge(
                $encounter,
                DischargeDisposition::TRANSFERRED,
                $label,
                $actedBy,
            );

            $this->releaseBed($from['bed_id'], $encounter->id, $actedBy);
            $this->releasePendingRequests($encounter, $actedBy);

            $event = $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredOut,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: $destinationType,
                destinationBranchId: $destinationBranchId,
                destinationLabel: $label,
            );

            if ($encounter->isInpatient()) {
                $this->dispatchAfterCommit(new PatientDischarged($encounter, $event));
            }

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
            $this->assertBedAvailable($bedId, patientGender: $this->genderOf($patient));

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

            $encounter = $this->occupyBed($encounter, $bedId, $actedBy);
            $encounter = $this->encounterService->beginInpatientCare($encounter, $actedBy);
            $this->encounterService->ensureParticipant($encounter, $actedBy, ParticipantRole::ATTENDING, $actedBy);

            $event = $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::TransferredIn,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
                destinationType: filled($fromBranchId) ? AdtDestinationType::Branch : AdtDestinationType::ExternalFacility,
                destinationBranchId: $fromBranchId,
                destinationLabel: $sourceLabel,
            );

            $this->dispatchAfterCommit(new PatientAdmitted($encounter, $event, 'transfer_in'));

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    /**
     * @throws DischargeBlockedException when readiness is enforced, blocking
     *                                   items remain, and no override reason is given
     */
    public function discharge(
        Encounter $encounter,
        ?DischargeDisposition $disposition = null,
        ?string $transferDestination = null,
        ?string $notes = null,
        ?int $actedBy = null,
        ?string $overrideReason = null,
        ?\DateTimeInterface $followUpAt = null,
        ?string $followUpProviderId = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $disposition, $transferDestination, $notes, $actedBy, $overrideReason, $followUpAt, $followUpProviderId) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $disposition ??= DischargeDisposition::COMPLETED;

            $override = $this->assertDischargeReady($encounter, $disposition, $overrideReason, $followUpAt, $actedBy);

            // A bedded patient who was never explicitly "started" (arrived/triaged) must
            // still be dischargeable; walk the encounter to IN_PROGRESS first.
            $encounter = $this->encounterService->ensureInProgress($encounter);

            $encounter = $this->encounterService->discharge(
                $encounter,
                $disposition,
                $transferDestination,
                $actedBy,
            );

            $metadata = $encounter->metadata ?? [];

            if (filled($notes)) {
                $metadata['discharge_notes'] = $notes;
            }

            if ($override !== null) {
                $metadata['discharge_override'] = $override;
            }

            if ($followUpAt !== null) {
                $metadata['follow_up'] = [
                    'at' => $followUpAt->format(DATE_ATOM),
                    'provider_id' => $followUpProviderId,
                    'set_by' => $actedBy,
                ];

                $encounter->dischargeSummary()->whereNull('follow_up_at')->update([
                    'follow_up_at' => $followUpAt,
                    'follow_up_provider_id' => $followUpProviderId,
                ]);
            }

            $encounter->forceFill(['metadata' => $metadata])->saveQuietly();

            $this->releaseBed($from['bed_id'], $encounter->id, $actedBy);
            $this->releasePendingRequests($encounter, $actedBy);

            $event = $this->logEvent(
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

            if ($encounter->isInpatient()) {
                $this->dispatchAfterCommit(new PatientDischarged(
                    $encounter,
                    $event,
                    $followUpAt ? Carbon::instance($followUpAt) : null,
                    $followUpProviderId,
                ));
            }

            return $encounter->fresh(['patient', 'branch']);
        });
    }

    /**
     * Inpatient discharges must pass the readiness checklist unless the
     * clinician records why they are overriding it. Transfers out and deaths
     * are exempt from blocking (the warnings are still recorded).
     *
     * @return array<string, mixed>|null the override record to store, if any
     */
    protected function assertDischargeReady(
        Encounter $encounter,
        DischargeDisposition $disposition,
        ?string $overrideReason,
        ?\DateTimeInterface $followUpAt,
        ?int $actedBy,
    ): ?array {
        if (! $encounter->isInpatient() || ! config('clinical.discharge.enforce_readiness', true)) {
            return null;
        }

        $readiness = app(DischargeReadinessService::class)->assess($encounter, [
            'follow_up_at' => $followUpAt?->format(DATE_ATOM),
        ]);

        if ($readiness->isReady()) {
            return null;
        }

        $exempt = in_array($disposition, [DischargeDisposition::TRANSFERRED, DischargeDisposition::DECEASED], true);

        if (! $exempt && blank($overrideReason)) {
            throw new DischargeBlockedException($readiness);
        }

        return [
            'reason' => $exempt && blank($overrideReason) ? __('Exempt: :disposition', ['disposition' => $disposition->getLabel()]) : $overrideReason,
            'items' => array_column($readiness->blocking(), 'key'),
            'by' => $actedBy,
            'at' => now()->toIso8601String(),
        ];
    }

    public function assignBed(
        Encounter $encounter,
        string $bedId,
        ?int $actedBy = null,
        ?string $notes = null,
        ?string $reservationReference = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $bedId, $actedBy, $notes, $reservationReference) {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $wasPlanned = $encounter->status === EncounterStatus::PLANNED;

            $encounter = $this->occupyBed($encounter, $bedId, $actedBy, $reservationReference);

            if ($encounter->isInpatient()) {
                $encounter = $this->encounterService->beginInpatientCare($encounter, $actedBy);
            }

            $event = $this->logEvent(
                encounter: $encounter,
                type: $wasPlanned ? AdtEventType::Admitted : AdtEventType::BedAssigned,
                from: $from,
                actedBy: $actedBy,
                notes: $notes,
            );

            if ($encounter->isInpatient() && $from['bed_id'] === null) {
                $this->dispatchAfterCommit(new PatientAdmitted($encounter, $event, $reservationReference ? 'accept' : 'assign'));
            }

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    /**
     * A clinician asks for the patient to be admitted to a ward. Nothing is
     * occupied yet: ward staff accept (and confirm the bed) or reject it.
     */
    public function requestAdmission(
        Encounter $encounter,
        string $wardId,
        ?string $bedId = null,
        ?string $notes = null,
        ?int $requestedBy = null,
    ): AdmissionRequest {
        return DB::transaction(function () use ($encounter, $wardId, $bedId, $notes, $requestedBy): AdmissionRequest {
            $requestedBy ??= Auth::id();
            $encounter = $encounter->fresh();

            if ($encounter->isCompleted()) {
                throw new \InvalidArgumentException(__('Cannot request admission for a completed encounter.'));
            }

            if (filled($encounter->bed_id)) {
                throw new \InvalidArgumentException(__('Patient already occupies a bed. Use an internal transfer instead.'));
            }

            if ($encounter->pendingAdmissionRequest()->exists()) {
                throw new \InvalidArgumentException(__('An admission request is already pending for this encounter.'));
            }

            $ward = Location::query()->find($wardId);

            if ($ward === null || (string) $ward->branch_id !== (string) $encounter->branch_id) {
                throw new \InvalidArgumentException(__('The selected ward does not belong to this encounter\'s branch.'));
            }

            $bed = null;

            if ($bedId !== null) {
                $bed = Location::query()->find($bedId);

                if ($bed === null || (string) $bed->parent_id !== (string) $ward->id) {
                    throw new \InvalidArgumentException(__('The preferred bed is not in the selected ward.'));
                }
            }

            $expiryHours = config('clinical.admissions.request_expiry_hours');

            $request = AdmissionRequest::query()->create([
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'branch_id' => $encounter->branch_id,
                'requested_ward_id' => $wardId,
                'requested_bed_id' => $bedId,
                'status' => AdmissionRequestStatus::Pending,
                'notes' => $notes,
                'requested_by' => $requestedBy,
                'requested_at' => now(),
                'expires_at' => $expiryHours ? now()->addHours((int) $expiryHours) : null,
            ]);

            if ($bed !== null
                && config('clinical.beds.reserve_on_request', true)
                && $bed->bedStatus()->isAssignable()
                && ! Encounter::active()->where('bed_id', $bed->id)->exists()) {
                $this->bedStatusService->reserve($bed, $request->id, __('Admission request'), $requestedBy);
            }

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::AdmissionRequested,
                from: $this->snapshot($encounter),
                actedBy: $requestedBy,
                notes: $notes,
                destinationType: AdtDestinationType::Branch,
                destinationBranchId: $encounter->branch_id,
                destinationLabel: $ward->name,
            );

            $this->dispatchAfterCommit(new AdmissionRequested($request));

            return $request->fresh(['encounter', 'requestedWard', 'requestedBed', 'requester']);
        });
    }

    /**
     * Ward staff accept the request and confirm the bed. The encounter becomes
     * an inpatient stay and the bed is occupied from this point.
     */
    public function acceptAdmission(
        AdmissionRequest $request,
        string $bedId,
        ?string $notes = null,
        ?int $actedBy = null,
    ): Encounter {
        return DB::transaction(function () use ($request, $bedId, $notes, $actedBy): Encounter {
            $actedBy ??= Auth::id();
            $request = $request->fresh(['encounter']);

            if (! $request->isPending()) {
                throw new \InvalidArgumentException(__('This admission request has already been decided.'));
            }

            $encounter = $request->encounter;

            if ($encounter->isCompleted()) {
                throw new \InvalidArgumentException(__('Cannot admit a completed encounter.'));
            }

            $bed = Location::query()->find($bedId);

            if ($bed === null || (string) $bed->parent_id !== (string) $request->requested_ward_id) {
                throw new \InvalidArgumentException(__('The bed must be in the requested ward.'));
            }

            if ($encounter->type === EncounterType::EMERGENCY && $encounter->status === EncounterStatus::ARRIVED) {
                throw new \InvalidArgumentException(__('Triage the patient before admitting them to a ward.'));
            }

            $this->assertBedAvailable($bedId, $encounter->id, $request->id, $this->genderOf($encounter->patient));

            if ($encounter->type !== EncounterType::INPATIENT) {
                $encounter->update([
                    'type' => EncounterType::INPATIENT,
                    'admitted_at' => $encounter->admitted_at ?? now(),
                ]);
                $encounter = $encounter->fresh();
            }

            // Whatever bed was reserved for this request is released unless it is the one chosen.
            if ($request->requested_bed_id !== null && $request->requested_bed_id !== $bedId) {
                $this->releaseReservation($request->requested_bed_id, $request->id, $actedBy);
            }

            $encounter = $this->assignBed($encounter, $bedId, $actedBy, $notes, $request->id);

            $this->encounterService->ensureParticipant($encounter, $request->requested_by, ParticipantRole::ATTENDING, $actedBy);
            $this->encounterService->ensureParticipant($encounter, $actedBy, ParticipantRole::NURSE, $actedBy);

            $request->update([
                'status' => AdmissionRequestStatus::Accepted,
                'assigned_bed_id' => $bedId,
                'decided_by' => $actedBy,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ]);

            $this->dispatchAfterCommit(new AdmissionAccepted($request, $encounter));

            return $encounter->fresh(['bed', 'location', 'department', 'patient']);
        });
    }

    /**
     * Ward staff decline the request. The encounter keeps its current state
     * so the clinician can request another ward or close the visit.
     */
    public function rejectAdmission(
        AdmissionRequest $request,
        string $reason,
        ?int $actedBy = null,
    ): AdmissionRequest {
        return DB::transaction(function () use ($request, $reason, $actedBy): AdmissionRequest {
            $actedBy ??= Auth::id();
            $request = $request->fresh(['encounter']);

            if (! $request->isPending()) {
                throw new \InvalidArgumentException(__('This admission request has already been decided.'));
            }

            $request->update([
                'status' => AdmissionRequestStatus::Rejected,
                'decided_by' => $actedBy,
                'decided_at' => now(),
                'decision_notes' => $reason,
            ]);

            $this->releaseReservation($request->requested_bed_id, $request->id, $actedBy);

            $encounter = $request->encounter;

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::AdmissionRejected,
                from: $this->snapshot($encounter),
                actedBy: $actedBy,
                notes: $reason,
                destinationType: AdtDestinationType::Branch,
                destinationBranchId: $encounter->branch_id,
                destinationLabel: $request->requestedWard?->name,
            );

            $this->dispatchAfterCommit(new AdmissionRejected($request));

            return $request->fresh(['encounter', 'requestedWard', 'decider']);
        });
    }

    /**
     * Records (or clears) the planned discharge date and keeps a short history
     * so slipped dates are visible.
     */
    public function setExpectedDischarge(Encounter $encounter, ?\DateTimeInterface $at, ?int $actedBy = null, ?string $reason = null): Encounter
    {
        $actedBy ??= Auth::id();
        $encounter = $encounter->fresh();

        if (! $encounter->isInpatient() || $encounter->isCompleted()) {
            throw new \InvalidArgumentException(__('Expected discharge only applies to an open inpatient stay.'));
        }

        $history = $encounter->metadata['expected_discharge_history'] ?? [];
        $history[] = [
            'at' => $at?->format(DATE_ATOM),
            'set_by' => $actedBy,
            'set_at' => now()->toIso8601String(),
            'reason' => $reason,
        ];

        $encounter->forceFill([
            'expected_discharge_at' => $at,
            'metadata' => array_merge($encounter->metadata ?? [], ['expected_discharge_history' => array_slice($history, -10)]),
        ])->save();

        return $encounter->fresh();
    }

    /**
     * Cancels the encounter and records the bed movement so the audit trail
     * shows how the bed was freed.
     */
    public function cancel(Encounter $encounter, ?string $reason = null, ?int $actedBy = null): Encounter
    {
        return DB::transaction(function () use ($encounter, $reason, $actedBy): Encounter {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $this->releasePendingRequests($encounter, $actedBy);

            $encounter = $this->encounterService->cancelEncounter($encounter, $reason);

            $this->releaseBed($from['bed_id'], $encounter->id, $actedBy);

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::Cancelled,
                from: $from,
                actedBy: $actedBy,
                notes: $reason,
            );

            return $encounter->fresh(['patient', 'branch']);
        });
    }

    /**
     * The patient leaves the ward temporarily; the bed stays theirs.
     */
    public function sendOnPass(
        Encounter $encounter,
        ?string $reason = null,
        ?\DateTimeInterface $expectedReturnAt = null,
        ?int $actedBy = null,
    ): Encounter {
        return DB::transaction(function () use ($encounter, $reason, $expectedReturnAt, $actedBy): Encounter {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();

            if (! $encounter->isInpatient() || blank($encounter->bed_id)) {
                throw new \InvalidArgumentException(__('Only an admitted inpatient can be sent on pass.'));
            }

            $from = $this->snapshot($encounter);
            $encounter = $this->encounterService->putOnLeave($encounter, $reason);

            $encounter->forceFill(['metadata' => array_merge($encounter->metadata ?? [], [
                'pass' => [
                    'reason' => $reason,
                    'started_at' => now()->toIso8601String(),
                    'expected_return_at' => $expectedReturnAt?->format(DATE_ATOM),
                    'sent_by' => $actedBy,
                ],
            ])])->saveQuietly();

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::OnPass,
                from: $from,
                actedBy: $actedBy,
                notes: $reason,
            );

            return $encounter->fresh(['bed', 'location', 'patient']);
        });
    }

    public function returnFromPass(Encounter $encounter, ?int $actedBy = null): Encounter
    {
        return DB::transaction(function () use ($encounter, $actedBy): Encounter {
            $actedBy ??= Auth::id();
            $encounter = $encounter->fresh();
            $from = $this->snapshot($encounter);

            $encounter = $this->encounterService->returnFromLeave($encounter);

            $pass = $encounter->metadata['pass'] ?? null;

            if (is_array($pass)) {
                $encounter->forceFill(['metadata' => array_merge($encounter->metadata ?? [], [
                    'pass' => array_merge($pass, ['returned_at' => now()->toIso8601String()]),
                ])])->saveQuietly();
            }

            $this->logEvent(
                encounter: $encounter,
                type: AdtEventType::ReturnedFromPass,
                from: $from,
                actedBy: $actedBy,
            );

            return $encounter->fresh(['bed', 'location', 'patient']);
        });
    }

    /**
     * The requester (or ward staff) withdraws a pending request.
     */
    public function cancelAdmissionRequest(AdmissionRequest $request, ?string $reason = null, ?int $actedBy = null): AdmissionRequest
    {
        return $this->closeRequest($request, AdmissionRequestStatus::Cancelled, AdtEventType::AdmissionCancelled, $reason, $actedBy);
    }

    public function expireAdmissionRequest(AdmissionRequest $request): AdmissionRequest
    {
        return $this->closeRequest($request, AdmissionRequestStatus::Expired, AdtEventType::AdmissionExpired, __('Expired without a decision'), null);
    }

    protected function closeRequest(
        AdmissionRequest $request,
        AdmissionRequestStatus $status,
        AdtEventType $eventType,
        ?string $reason,
        ?int $actedBy,
    ): AdmissionRequest {
        return DB::transaction(function () use ($request, $status, $eventType, $reason, $actedBy): AdmissionRequest {
            $request = $request->fresh(['encounter', 'requestedWard']);

            if (! $request->isPending()) {
                throw new \InvalidArgumentException(__('This admission request has already been decided.'));
            }

            $request->update([
                'status' => $status,
                'decided_by' => $actedBy,
                'decided_at' => now(),
                'decision_notes' => $reason,
            ]);

            $this->releaseReservation($request->requested_bed_id, $request->id, $actedBy);

            $encounter = $request->encounter;

            if ($encounter !== null) {
                $this->logEvent(
                    encounter: $encounter,
                    type: $eventType,
                    from: $this->snapshot($encounter),
                    actedBy: $actedBy,
                    notes: $reason,
                    destinationType: AdtDestinationType::Branch,
                    destinationBranchId: $encounter->branch_id,
                    destinationLabel: $request->requestedWard?->name,
                );
            }

            $this->dispatchAfterCommit(new AdmissionRequestCancelled($request, $status === AdmissionRequestStatus::Expired));

            return $request->fresh(['encounter', 'requestedWard', 'decider']);
        });
    }

    /**
     * Occupies the bed for the encounter: validates, records bed + ward on
     * the encounter, and marks the bed occupied.
     */
    protected function occupyBed(Encounter $encounter, string $bedId, ?int $actedBy, ?string $reservationReference = null): Encounter
    {
        $bed = $this->bedAssignmentService->assertAssignable(
            $bedId,
            $encounter->branch_id,
            $encounter->id,
            $reservationReference,
            $this->genderOf($encounter->patient),
        );

        $encounter->update([
            'bed_id' => $bedId,
            'location_id' => $bed->parent_id ?? $encounter->location_id,
        ]);

        $this->bedStatusService->markOccupied($bed, $encounter->id, $reservationReference, $actedBy);

        return $encounter->fresh();
    }

    protected function releaseBed(?string $bedId, string $encounterId, ?int $actedBy): void
    {
        if ($bedId === null) {
            return;
        }

        $bed = Location::withoutGlobalScope('branch')->find($bedId);

        if ($bed === null || ! $bed->isBed()) {
            return;
        }

        $this->bedStatusService->release(
            $bed,
            $encounterId,
            (bool) config('clinical.beds.cleaning_on_discharge', true),
            $actedBy,
        );
    }

    protected function releaseReservation(?string $bedId, string $requestId, ?int $actedBy): void
    {
        if ($bedId === null) {
            return;
        }

        $bed = Location::withoutGlobalScope('branch')->find($bedId);

        if ($bed !== null && $bed->isBed()) {
            $this->bedStatusService->releaseReservation($bed, $requestId, $actedBy);
        }
    }

    /**
     * Any request still pending when the encounter closes is withdrawn.
     */
    protected function releasePendingRequests(Encounter $encounter, ?int $actedBy): void
    {
        foreach ($encounter->admissionRequests()->pending()->get() as $request) {
            $request->update([
                'status' => AdmissionRequestStatus::Cancelled,
                'decided_by' => $actedBy,
                'decided_at' => now(),
                'decision_notes' => __('Encounter closed'),
            ]);

            $this->releaseReservation($request->requested_bed_id, $request->id, $actedBy);
        }
    }

    /**
     * Domain events fire once the outermost transaction commits (and never on
     * rollback), so listeners always see committed rows.
     */
    protected function dispatchAfterCommit(object $event): void
    {
        DB::afterCommit(fn () => event($event));
    }

    protected function genderOf(?Patient $patient): ?string
    {
        $gender = $patient?->gender;

        if ($gender === null) {
            return null;
        }

        return (string) enum_value($gender);
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

    protected function assertBedAvailable(
        string $bedId,
        ?string $exceptEncounterId = null,
        ?string $forRequestId = null,
        ?string $patientGender = null,
        ?string $branchId = null,
    ): Location {
        return $this->bedAssignmentService->assertAssignable($bedId, $branchId, $exceptEncounterId, $forRequestId, $patientGender);
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
