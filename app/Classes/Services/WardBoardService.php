<?php

namespace Modules\Clinical\Classes\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Models\AdmissionRequest;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Enums\LocationType;
use Modules\Core\Models\Location;
use Modules\Core\Support\ModuleAvailability;

/**
 * Builds the serialisable payload behind the Ward Board: every bed in a ward
 * with its status, the patient occupying it, and the flags that need a
 * nurse's attention. Queries are batched per ward, not per bed.
 */
class WardBoardService
{
    public function __construct(
        protected BedAssignmentService $bedAssignmentService,
        protected WardBoardAlertResolver $alertResolver,
    ) {}

    /**
     * @return Collection<string, string> ward id => name
     */
    public function wardsForBranch(string $branchId): Collection
    {
        return $this->bedAssignmentService->getWardsForBranch($branchId);
    }

    /**
     * @return array{ward: array<string, mixed>, stats: array<string, mixed>, beds: list<array<string, mixed>>, incoming: list<array<string, mixed>>}
     */
    public function buildWard(Location $ward, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $ward->loadMissing('nurseInCharge');

        $beds = Location::withoutGlobalScope('branch')
            ->where('parent_id', $ward->id)
            ->where('type', LocationType::BED->value)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $encounters = Encounter::query()
            ->whereIn('bed_id', $beds->pluck('id'))
            ->whereNotIn('status', [EncounterStatus::FINISHED->value, EncounterStatus::CANCELLED->value])
            ->with(['patient.latestVitals', 'activeParticipants.user'])
            ->get()
            ->keyBy('bed_id');

        $doseTimes = $this->pendingDoseTimes($encounters);

        $reservations = AdmissionRequest::query()
            ->pending()
            ->whereIn('requested_bed_id', $beds->pluck('id'))
            ->with('patient')
            ->get()
            ->keyBy('requested_bed_id');

        $bedCards = $beds->map(function (Location $bed) use ($encounters, $reservations, $doseTimes, $now): array {
            $encounter = $encounters->get($bed->id);
            $status = $encounter ? BedStatus::OCCUPIED : $bed->bedStatus();
            $reservation = $reservations->get($bed->id);

            return [
                'id' => $bed->id,
                'name' => $bed->name,
                'code' => $bed->code,
                'bed_class' => $bed->bed_class,
                'status' => $status->value,
                'status_label' => (string) $status->getLabel(),
                'status_color' => (string) $status->getColor(),
                'status_reason' => $bed->status_reason,
                'status_changed_at' => $bed->status_changed_at?->toIso8601String(),
                'manual_transitions' => array_map(fn (BedStatus $s): string => $s->value, $status->manualTransitions()),
                'reserved_for' => $reservation ? [
                    'request_id' => $reservation->id,
                    'patient_name' => $reservation->patient?->full_name,
                ] : null,
                'encounter' => $encounter ? $this->encounterCard($encounter, $doseTimes[$encounter->id] ?? [], $now) : null,
            ];
        })->values()->all();

        return [
            'ward' => [
                'id' => $ward->id,
                'name' => $ward->name,
                'code' => $ward->code,
                'capacity' => $ward->capacity,
                'gender_policy' => $ward->genderPolicy()->value,
                'gender_policy_label' => (string) $ward->genderPolicy()->getLabel(),
                'bed_class' => $ward->bed_class,
                'specialty' => $ward->specialty,
                'nurse_in_charge' => $ward->nurseInCharge ? ['id' => $ward->nurseInCharge->id, 'name' => $ward->nurseInCharge->name] : null,
            ],
            'stats' => $this->stats($ward, $bedCards, $now),
            'beds' => $bedCards,
            'incoming' => $this->incoming($ward),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $bedCards
     * @return array<string, int|float>
     */
    protected function stats(Location $ward, array $bedCards, CarbonInterface $now): array
    {
        $counts = collect($bedCards)->countBy('status');
        $total = count($bedCards);
        $occupied = (int) $counts->get(BedStatus::OCCUPIED->value, 0);

        $todayStart = $now->copy()->startOfDay();

        return [
            'beds_total' => $total,
            'occupied' => $occupied,
            'available' => (int) $counts->get(BedStatus::AVAILABLE->value, 0),
            'reserved' => (int) $counts->get(BedStatus::RESERVED->value, 0),
            'cleaning' => (int) $counts->get(BedStatus::CLEANING->value, 0),
            'blocked' => (int) $counts->get(BedStatus::BLOCKED->value, 0),
            'occupancy_pct' => $total > 0 ? (int) round($occupied / $total * 100) : 0,
            'admissions_today' => EncounterLocationEvent::query()
                ->where('to_location_id', $ward->id)
                ->whereIn('event_type', [AdtEventType::Admitted->value, AdtEventType::TransferredIn->value, AdtEventType::TransferredInternal->value])
                ->where('occurred_at', '>=', $todayStart)
                ->count(),
            'discharges_today' => EncounterLocationEvent::query()
                ->where('from_location_id', $ward->id)
                ->whereIn('event_type', [AdtEventType::Discharged->value, AdtEventType::TransferredOut->value])
                ->where('occurred_at', '>=', $todayStart)
                ->count(),
            'pending_requests' => AdmissionRequest::query()->pending()->where('requested_ward_id', $ward->id)->count(),
            'long_stay' => collect($bedCards)->filter(fn (array $bed): bool => collect($bed['encounter']['alerts'] ?? [])->contains(fn (array $alert): bool => in_array($alert['key'], ['long_stay', 'past_expected_discharge'], true)))->count(),
        ];
    }

    /**
     * @param  list<string>  $doseTimes
     * @return array<string, mixed>
     */
    protected function encounterCard(Encounter $encounter, array $doseTimes, CarbonInterface $now): array
    {
        $patient = $encounter->patient;
        $participants = $encounter->activeParticipants;

        $attending = $participants->first(fn ($p) => in_array($p->role, [ParticipantRole::ATTENDING, ParticipantRole::PRIMARY_PROVIDER], true));
        $nurse = $participants->first(fn ($p) => $p->role === ParticipantRole::NURSE);

        return [
            'id' => $encounter->id,
            'number' => $encounter->encounter_number,
            'status' => $encounter->status?->value,
            'status_label' => $encounter->status?->getLabel(),
            'priority' => $encounter->priority?->value,
            'on_pass' => $encounter->status === EncounterStatus::ON_LEAVE,
            'admitted_at' => $encounter->admitted_at?->toIso8601String(),
            'admitted_label' => $encounter->admitted_at?->format('D j M, H:i'),
            'los_days' => $encounter->los_days,
            'los_label' => $encounter->duration,
            'expected_discharge_at' => $encounter->expected_discharge_at?->toIso8601String(),
            'expected_discharge_label' => $encounter->expected_discharge_at?->format('D j M'),
            'chief_complaint' => $encounter->chief_complaint,
            'patient' => $patient ? [
                'id' => $patient->id,
                'mrn' => $patient->mrn,
                'name' => $patient->full_name,
                'age' => $patient->age,
                'gender' => is_object($patient->gender) && isset($patient->gender->value) ? $patient->gender->value : $patient->gender,
            ] : null,
            'attending' => $attending?->user ? ['id' => $attending->user->id, 'name' => $attending->user->name] : null,
            'nurse' => $nurse?->user ? ['id' => $nurse->user->id, 'name' => $nurse->user->name] : null,
            'alerts' => $this->alertResolver->forEncounter($encounter, $now, $doseTimes),
        ];
    }

    /**
     * Pending in-facility dose times for every encounter on the ward, one query.
     *
     * @param  Collection<string, Encounter>  $encounters
     * @return array<string, list<string>>
     */
    protected function pendingDoseTimes(Collection $encounters): array
    {
        if ($encounters->isEmpty() || ! ModuleAvailability::pharmacyEnabled()) {
            return [];
        }

        $items = RequestItem::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereHas('serviceRequest', fn ($q) => $q->whereIn('encounter_id', $encounters->pluck('id')))
            ->whereHas('prescriptionDetail', fn ($q) => $q->where('administration_context', 'in_facility')->whereNotNull('next_dose_at'))
            ->with(['serviceRequest:id,encounter_id', 'prescriptionDetail'])
            ->get();

        $times = [];

        foreach ($items as $item) {
            $encounterId = $item->serviceRequest?->encounter_id;
            $nextDose = $item->prescriptionDetail?->next_dose_at;

            if ($encounterId === null || $nextDose === null) {
                continue;
            }

            $times[$encounterId][] = \Illuminate\Support\Carbon::parse($nextDose)->toIso8601String();
        }

        return $times;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function incoming(Location $ward): array
    {
        return AdmissionRequest::query()
            ->pending()
            ->where('requested_ward_id', $ward->id)
            ->with(['patient', 'requester', 'requestedBed', 'encounter'])
            ->orderBy('requested_at')
            ->get()
            ->map(fn (AdmissionRequest $request): array => [
                'id' => $request->id,
                'encounter_id' => $request->encounter_id,
                'patient_id' => $request->patient_id,
                'patient_name' => $request->patient?->full_name,
                'encounter_number' => $request->encounter?->encounter_number,
                'requested_by' => $request->requester?->name,
                'requested_at' => $request->requested_at?->toIso8601String(),
                'requested_label' => $request->requested_at?->diffForHumans(),
                'expires_at' => $request->expires_at?->toIso8601String(),
                'expires_label' => $request->expires_at?->diffForHumans(),
                'preferred_bed' => $request->requestedBed?->name,
                'notes' => $request->notes,
            ])
            ->all();
    }
}
