<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\WardStatementPeriod;
use Modules\Clinical\Enums\WardStatementStatus;
use Modules\Clinical\Models\DailyWardStatement;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Core\Enums\BedStatus;
use Modules\Core\Enums\LocationType;
use Modules\Core\Models\Location;

/**
 * Computes and manages Daily Ward Statements (GHS Daily Bed State / Ward Return).
 *
 * Reconciliation invariant:
 *   remaining = brought_forward + admissions + transfers_in
 *             - discharges - transfers_out - deaths - lama_absconded
 */
class DailyWardStatementService
{
    /**
     * Resolve [start, end] timestamps for a given date and statement period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodWindow(CarbonInterface $date, WardStatementPeriod $period, ?int $cutoffHour = null): array
    {
        $cutoff = $cutoffHour ?? (int) config('clinical.wards.daily_statement_cutoff_hour', 0);
        $dayStart = Carbon::parse($date->toDateString())->startOfDay();

        return match ($period) {
            WardStatementPeriod::DAILY => [
                $dayStart->copy()->addHours($cutoff),
                $dayStart->copy()->addHours($cutoff)->addDay()->subSecond(),
            ],
            WardStatementPeriod::MORNING => [
                $dayStart->copy()->setTime(8, 0, 0),
                $dayStart->copy()->setTime(13, 59, 59),
            ],
            WardStatementPeriod::AFTERNOON => [
                $dayStart->copy()->setTime(14, 0, 0),
                $dayStart->copy()->setTime(19, 59, 59),
            ],
            WardStatementPeriod::NIGHT => [
                $dayStart->copy()->setTime(20, 0, 0),
                $dayStart->copy()->addDay()->setTime(7, 59, 59),
            ],
        };
    }

    /**
     * Compute live metrics & snapshot for a ward over a date/period without persisting.
     *
     * @return array<string, mixed>
     */
    public function computeMetrics(
        Location $ward,
        CarbonInterface $date,
        WardStatementPeriod $period = WardStatementPeriod::DAILY,
        ?int $cutoffHour = null,
    ): array {
        [$start, $end] = $this->periodWindow($date, $period, $cutoffHour);

        $beds = Location::withoutGlobalScope('branch')
            ->where('parent_id', $ward->id)
            ->where('type', LocationType::BED->value)
            ->where('is_active', true)
            ->get();

        $bedIds = $beds->pluck('id');
        $totalBeds = $beds->count();
        $blockedBeds = $beds->filter(fn (Location $bed): bool => $bed->bedStatus() === BedStatus::BLOCKED)->count();

        // Active encounters currently on this ward
        $activeEncounters = Encounter::query()
            ->where(function ($q) use ($ward, $bedIds) {
                $q->where('location_id', $ward->id)
                    ->when($bedIds->isNotEmpty(), fn ($b) => $b->orWhereIn('bed_id', $bedIds));
            })
            ->whereNotIn('status', [EncounterStatus::FINISHED->value, EncounterStatus::CANCELLED->value])
            ->with(['patient', 'bed'])
            ->get();

        $liveOccupiedCount = $activeEncounters->count();
        $onPassCount = $activeEncounters->where('status', EncounterStatus::ON_LEAVE)->count();

        // Movement events within the period window
        $incomingEvents = EncounterLocationEvent::query()
            ->where('to_location_id', $ward->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type', [
                AdtEventType::Admitted->value,
                AdtEventType::TransferredIn->value,
                AdtEventType::TransferredInternal->value,
            ])
            ->with(['patient', 'encounter'])
            ->get();

        $admissions = $incomingEvents->where('event_type', AdtEventType::Admitted);
        $transfersIn = $incomingEvents->filter(function (EncounterLocationEvent $e) use ($ward): bool {
            if ($e->event_type === AdtEventType::TransferredIn) {
                return true;
            }

            // Internal transfer counts as Transfer In only if coming from a different ward
            return $e->event_type === AdtEventType::TransferredInternal
                && $e->from_location_id !== $ward->id;
        });

        $outgoingEvents = EncounterLocationEvent::query()
            ->where('from_location_id', $ward->id)
            ->whereBetween('occurred_at', [$start, $end])
            ->whereIn('event_type', [
                AdtEventType::Discharged->value,
                AdtEventType::TransferredOut->value,
                AdtEventType::TransferredInternal->value,
            ])
            ->with(['patient', 'encounter'])
            ->get();

        $transfersOut = $outgoingEvents->filter(function (EncounterLocationEvent $e) use ($ward): bool {
            if ($e->event_type === AdtEventType::TransferredOut) {
                return true;
            }

            return $e->event_type === AdtEventType::TransferredInternal
                && $e->to_location_id !== $ward->id;
        });

        $dischargeEvents = $outgoingEvents->where('event_type', AdtEventType::Discharged);
        $dischargeEncounterIds = $dischargeEvents->pluck('encounter_id')->filter()->unique()->values();

        $dispositionsByEncounter = $dischargeEncounterIds->isNotEmpty()
            ? DischargeSummary::query()
                ->whereIn('encounter_id', $dischargeEncounterIds)
                ->pluck('discharge_disposition', 'encounter_id')
            : collect();

        $normalDischarges = 0;
        $deaths = 0;
        $lamaAbsconded = 0;

        foreach ($dischargeEvents as $event) {
            $disposition = $dispositionsByEncounter->get($event->encounter_id);
            $dispValue = $disposition instanceof DischargeDisposition ? $disposition->value : (string) $disposition;

            if ($dispValue === DischargeDisposition::DECEASED->value) {
                $deaths++;
            } elseif ($dispValue === DischargeDisposition::AGAINST_ADVICE->value) {
                $lamaAbsconded++;
            } else {
                $normalDischarges++;
            }
        }

        // Brought forward: seed from immediately preceding submitted/reviewed statement for this ward
        $previousStatement = DailyWardStatement::query()
            ->where('location_id', $ward->id)
            ->where('period_end_at', '<', $start)
            ->whereIn('status', [
                WardStatementStatus::SUBMITTED->value,
                WardStatementStatus::REVIEWED->value,
                WardStatementStatus::AMENDED->value,
            ])
            ->orderByDesc('period_end_at')
            ->first();

        $admissionsCount = $admissions->count();
        $transfersInCount = $transfersIn->count();
        $transfersOutCount = $transfersOut->count();

        if ($previousStatement !== null) {
            $broughtForward = $previousStatement->remaining_total;
        } else {
            // Reconstruct opening balance from live occupied count if this is the ward's first statement
            $broughtForward = max(
                0,
                $liveOccupiedCount - $admissionsCount - $transfersInCount
                    + $normalDischarges + $transfersOutCount + $deaths + $lamaAbsconded
            );
        }

        $remainingTotal = max(
            0,
            $broughtForward + $admissionsCount + $transfersInCount
                - $normalDischarges - $transfersOutCount - $deaths - $lamaAbsconded
        );

        $discrepancy = $liveOccupiedCount - $remainingTotal;

        // Demographics breakdown (GHS-aligned)
        $demographics = [
            'male' => 0,
            'female' => 0,
            'other_gender' => 0,
            'under_5' => 0,
            'age_5_14' => 0,
            'adult_15_plus' => 0,
        ];

        $criticalPatients = [];

        foreach ($activeEncounters as $encounter) {
            $patient = $encounter->patient;
            if (! $patient) {
                continue;
            }

            $gender = strtolower((string) enum_value($patient->gender));
            if (in_array($gender, ['male', 'm'], true)) {
                $demographics['male']++;
            } elseif (in_array($gender, ['female', 'f'], true)) {
                $demographics['female']++;
            } else {
                $demographics['other_gender']++;
            }

            $age = $patient->age;
            if ($age !== null && $age < 5) {
                $demographics['under_5']++;
            } elseif ($age !== null && $age <= 14) {
                $demographics['age_5_14']++;
            } else {
                $demographics['adult_15_plus']++;
            }

            $priority = strtolower((string) enum_value($encounter->priority));
            if (in_array($priority, ['emergency', 'urgent', 'critical', 'stat'], true)) {
                $criticalPatients[] = [
                    'encounter_id' => $encounter->id,
                    'patient_name' => $patient->full_name,
                    'mrn' => $patient->mrn,
                    'bed' => $encounter->bed?->name,
                    'priority' => $priority,
                    'chief_complaint' => $encounter->chief_complaint,
                ];
            }
        }

        $patientSnapshot = [
            'admissions' => $admissions->map(fn (EncounterLocationEvent $e): array => [
                'patient_name' => $e->patient?->full_name,
                'mrn' => $e->patient?->mrn,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'transfers_in' => $transfersIn->map(fn (EncounterLocationEvent $e): array => [
                'patient_name' => $e->patient?->full_name,
                'mrn' => $e->patient?->mrn,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'discharges' => $dischargeEvents->map(fn (EncounterLocationEvent $e): array => [
                'patient_name' => $e->patient?->full_name,
                'mrn' => $e->patient?->mrn,
                'disposition' => enum_value($dispositionsByEncounter->get($e->encounter_id)) ?? DischargeDisposition::COMPLETED->value,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'transfers_out' => $transfersOut->map(fn (EncounterLocationEvent $e): array => [
                'patient_name' => $e->patient?->full_name,
                'mrn' => $e->patient?->mrn,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
            'critically_ill' => $criticalPatients,
        ];

        return [
            'period_start_at' => $start,
            'period_end_at' => $end,
            'total_beds' => $totalBeds,
            'blocked_beds' => $blockedBeds,
            'floor_or_extra_beds' => max(0, $liveOccupiedCount - max(0, $totalBeds - $blockedBeds)),
            'brought_forward' => $broughtForward,
            'admissions_count' => $admissionsCount,
            'transfers_in_count' => $transfersInCount,
            'discharges_count' => $normalDischarges,
            'transfers_out_count' => $transfersOutCount,
            'deaths_count' => $deaths,
            'lama_absconded_count' => $lamaAbsconded,
            'on_pass_count' => $onPassCount,
            'remaining_total' => $remainingTotal,
            'live_occupied_count' => $liveOccupiedCount,
            'discrepancy' => $discrepancy,
            'demographics_breakdown' => $demographics,
            'patient_snapshot' => $patientSnapshot,
        ];
    }

    /**
     * Create or refresh a draft Daily Ward Statement for the given ward/date/period.
     */
    public function generateOrRefreshDraft(
        Location $ward,
        CarbonInterface $date,
        WardStatementPeriod $period = WardStatementPeriod::DAILY,
        ?User $preparedBy = null,
    ): DailyWardStatement {
        $preparedBy ??= Auth::user();
        $dateString = $date->toDateString();

        $existing = DailyWardStatement::query()
            ->where('location_id', $ward->id)
            ->whereDate('statement_date', $dateString)
            ->where('period_type', $period->value)
            ->first();

        if ($existing !== null && ! $existing->status->isEditable()) {
            return $existing;
        }

        $metrics = $this->computeMetrics($ward, $date, $period);

        $attributes = [
            'branch_id' => $ward->branch_id,
            'period_start_at' => $metrics['period_start_at'],
            'period_end_at' => $metrics['period_end_at'],
            'total_beds' => $metrics['total_beds'],
            'blocked_beds' => $metrics['blocked_beds'],
            'floor_or_extra_beds' => $metrics['floor_or_extra_beds'],
            'brought_forward' => $metrics['brought_forward'],
            'admissions_count' => $metrics['admissions_count'],
            'transfers_in_count' => $metrics['transfers_in_count'],
            'discharges_count' => $metrics['discharges_count'],
            'transfers_out_count' => $metrics['transfers_out_count'],
            'deaths_count' => $metrics['deaths_count'],
            'lama_absconded_count' => $metrics['lama_absconded_count'],
            'on_pass_count' => $metrics['on_pass_count'],
            'remaining_total' => $metrics['remaining_total'],
            'demographics_breakdown' => $metrics['demographics_breakdown'],
            'patient_snapshot' => $metrics['patient_snapshot'],
            'prepared_by' => $preparedBy?->id ?? $existing?->prepared_by,
        ];

        if ($existing !== null) {
            $existing->update($attributes);

            return $existing->fresh();
        }

        return DailyWardStatement::create(array_merge($attributes, [
            'location_id' => $ward->id,
            'statement_date' => $dateString,
            'period_type' => $period->value,
            'status' => WardStatementStatus::DRAFT->value,
        ]));
    }

    /**
     * Freeze and submit a Daily Ward Statement.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function submit(DailyWardStatement $statement, array $overrides = [], ?User $submittedBy = null): DailyWardStatement
    {
        if (! $statement->status->isEditable()) {
            throw new \RuntimeException('This daily ward statement has already been submitted and cannot be modified without an amendment.');
        }

        $submittedBy ??= Auth::user();

        $allowedFields = [
            'brought_forward',
            'admissions_count',
            'transfers_in_count',
            'discharges_count',
            'transfers_out_count',
            'deaths_count',
            'lama_absconded_count',
            'on_pass_count',
            'floor_or_extra_beds',
            'critically_ill_notes',
            'special_cases_notes',
            'general_remarks',
        ];

        $updates = array_intersect_key($overrides, array_flip($allowedFields));

        $statement->fill($updates);
        $statement->remaining_total = $statement->computedRemaining();
        $statement->status = WardStatementStatus::SUBMITTED;
        $statement->submitted_by = $submittedBy?->id;
        $statement->submitted_at = now();
        $statement->save();

        return $statement->fresh();
    }

    /**
     * Matron / Nursing Administration review sign-off.
     */
    public function review(DailyWardStatement $statement, ?string $reviewNotes = null, ?User $reviewedBy = null): DailyWardStatement
    {
        if ($statement->status !== WardStatementStatus::SUBMITTED) {
            throw new \RuntimeException('Only submitted statements can be reviewed.');
        }

        $reviewedBy ??= Auth::user();

        $statement->update([
            'status' => WardStatementStatus::REVIEWED,
            'reviewed_by' => $reviewedBy?->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        return $statement->fresh();
    }

    /**
     * Re-open a submitted/reviewed statement for audited amendment.
     */
    public function reopenForAmendment(DailyWardStatement $statement, string $reason): DailyWardStatement
    {
        $statement->update([
            'status' => WardStatementStatus::AMENDED,
            'amendment_reason' => $reason,
        ]);

        return $statement->fresh();
    }

    /**
     * Hospital-wide Daily Bed State rollup across all wards in a branch for a given date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function branchRollup(string $branchId, CarbonInterface $date, WardStatementPeriod $period = WardStatementPeriod::DAILY): Collection
    {
        $wards = Location::withoutGlobalScope('branch')
            ->wards()
            ->active()
            ->where('branch_id', $branchId)
            ->with('nurseInCharge')
            ->orderBy('name')
            ->get();

        $statements = DailyWardStatement::query()
            ->where('branch_id', $branchId)
            ->whereDate('statement_date', $date->toDateString())
            ->where('period_type', $period->value)
            ->get()
            ->keyBy('location_id');

        return $wards->map(function (Location $ward) use ($statements, $date, $period): array {
            $statement = $statements->get($ward->id);

            if ($statement !== null) {
                return [
                    'ward_id' => $ward->id,
                    'ward_name' => $ward->name,
                    'incharge' => $ward->nurseInCharge?->name,
                    'statement_id' => $statement->id,
                    'status' => $statement->status->value,
                    'status_label' => (string) $statement->status->getLabel(),
                    'total_beds' => $statement->total_beds,
                    'blocked_beds' => $statement->blocked_beds,
                    'brought_forward' => $statement->brought_forward,
                    'admissions' => $statement->admissions_count,
                    'transfers_in' => $statement->transfers_in_count,
                    'discharges' => $statement->discharges_count,
                    'transfers_out' => $statement->transfers_out_count,
                    'deaths' => $statement->deaths_count,
                    'lama_absconded' => $statement->lama_absconded_count,
                    'remaining' => $statement->remaining_total,
                    'occupancy_pct' => $statement->occupancyRate(),
                ];
            }

            $live = $this->computeMetrics($ward, $date, $period);
            $usable = max(0, $live['total_beds'] - $live['blocked_beds']);

            return [
                'ward_id' => $ward->id,
                'ward_name' => $ward->name,
                'incharge' => $ward->nurseInCharge?->name,
                'statement_id' => null,
                'status' => 'not_started',
                'status_label' => 'Not Started',
                'total_beds' => $live['total_beds'],
                'blocked_beds' => $live['blocked_beds'],
                'brought_forward' => $live['brought_forward'],
                'admissions' => $live['admissions_count'],
                'transfers_in' => $live['transfers_in_count'],
                'discharges' => $live['discharges_count'],
                'transfers_out' => $live['transfers_out_count'],
                'deaths' => $live['deaths_count'],
                'lama_absconded' => $live['lama_absconded_count'],
                'remaining' => $live['remaining_total'],
                'occupancy_pct' => $usable > 0 ? round(($live['remaining_total'] / $usable) * 100, 1) : 0.0,
            ];
        });
    }
}
