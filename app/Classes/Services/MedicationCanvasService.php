<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Support\RequestItemTableEnricher;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Enums\MedicationSlotStatus;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Support\ModuleAvailability;
use Modules\Core\Support\OptionalClass;

/**
 * Builds the node payload for the medication canvas: one lane per in-facility
 * prescription on an encounter, with its dose slots and administration history.
 *
 * Pharmacy owns the prescription detail, so it is only ever handled here as an
 * untyped object and its enums as strings — Clinical must not import Pharmacy.
 */
class MedicationCanvasService
{
    public function __construct(
        protected MedicationDoseScheduleService $scheduleService,
        protected MedicationFulfillmentPolicy $policy,
        protected MedicationSlotStatusResolver $statusResolver,
    ) {}

    public function isAvailable(): bool
    {
        return ModuleAvailability::pharmacyEnabled();
    }

    /**
     * @return array{nodes: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function buildNodes(Encounter $encounter, ?User $user = null): array
    {
        $now = now();
        $items = $this->queryItems($encounter);

        RequestItemTableEnricher::applyFinancialHolds($items);

        $nodes = $items
            ->map(fn (RequestItem $item): array => $this->buildNode($item, $user, $now))
            ->sortBy(fn (array $node): string => ($node['nextDueAt'] ?? '9999').'|'.$node['name'])
            ->values()
            ->all();

        return [
            'nodes' => $nodes,
            'meta' => [
                'now' => $now->toIso8601String(),
                'timezone' => config('app.timezone'),
                'window' => $this->window($nodes, $now),
                'leadMinutes' => $this->statusResolver->leadMinutes(),
                'graceMinutes' => $this->statusResolver->graceMinutes(),
                'encounterId' => $encounter->id,
                'encounterLabel' => $encounter->encounter_number,
            ],
        ];
    }

    /**
     * @return Collection<int, RequestItem>
     */
    protected function queryItems(Encounter $encounter): Collection
    {
        return RequestItem::query()
            ->whereHas('serviceRequest', fn ($query) => $query
                ->where('encounter_id', $encounter->id)
                ->where('patient_id', $encounter->patient_id))
            ->whereHas('prescriptionDetail', fn ($query) => $query->where('administration_context', 'in_facility'))
            ->with([
                'service',
                'prescriptionDetail.doseUnit',
                'serviceRequest.encounter',
                'medicationAdministrations.administeredBy',
                'medicationAdministrations.doseUnit',
            ])
            ->withFulfillmentAggregates()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildNode(RequestItem $item, ?User $user, CarbonInterface $now): array
    {
        $detail = $item->prescriptionDetail;
        $frequency = $this->enumValue($detail?->frequency);
        $isStat = in_array($frequency, ['stat', 'once'], true);
        $isPrn = (bool) ($detail?->prn ?? false) || $frequency === 'prn';
        $canRecord = $user !== null
            && ! $item->hasActiveFinancialHold()
            && $this->policy->canRecordMar($item, $user);

        $administrations = $item->medicationAdministrations
            ->sortBy('started_at')
            ->values();

        $bySequence = $administrations
            ->filter(fn (MedicationAdministration $administration): bool => $administration->dose_slot_sequence !== null)
            ->groupBy(fn (MedicationAdministration $administration): int => (int) $administration->dose_slot_sequence)
            ->map(fn (Collection $group): MedicationAdministration => $group
                ->sortByDesc(fn (MedicationAdministration $administration): int => $administration->countsAsGiven() ? 1 : 0)
                ->first());

        $nextDue = $detail ? $this->scheduleService->getNextDueSlot($item) : null;
        $slots = [];

        foreach ($detail ? $this->scheduleService->getSchedule($detail) : [] as $slot) {
            $administration = $bySequence->get($slot->sequence);
            $status = $this->statusResolver->resolve($slot->dueAt, $administration, $isStat, $now);

            $slots[] = [
                'sequence' => $slot->sequence,
                'dueAt' => $slot->dueAt->toIso8601String(),
                'dueAtLabel' => $slot->dueAt->format('H:i'),
                'status' => $status->value,
                'actionable' => $canRecord && $nextDue !== null && $slot->sequence === $nextDue->sequence,
                'administration' => $administration ? $this->administrationPayload($administration) : null,
            ];
        }

        $nextDueSlot = $nextDue
            ? collect($slots)->firstWhere('sequence', $nextDue->sequence)
            : null;

        $unslotted = $administrations
            ->filter(fn (MedicationAdministration $administration): bool => $administration->dose_slot_sequence === null)
            ->map(fn (MedicationAdministration $administration): array => $this->administrationPayload($administration))
            ->values()
            ->all();

        $givenCount = $this->policy->givenDosesCount($item);
        $total = $detail?->total_administrations;

        return [
            'id' => 'rx_'.$item->id,
            'kind' => 'prescription',
            'requestItemId' => $item->id,
            'name' => $item->service?->name ?? 'Medication',
            'doseLabel' => $this->doseLabel($detail),
            'route' => $this->enumValue($detail?->route),
            'routeLabel' => $this->enumLabel('Modules\\Pharmacy\\Enums\\MedicationRoute', $detail?->route),
            'frequency' => $frequency,
            'frequencyLabel' => $this->enumLabel('Modules\\Pharmacy\\Enums\\MedicationFrequency', $detail?->frequency),
            'prn' => $isPrn,
            'status' => enum_string($item->status) ?? '',
            'statusLabel' => $item->status?->getLabel() ?? (string) $item->status,
            'isTerminal' => $item->isTerminal(),
            'isControlled' => $this->policy->isControlledMedication($item),
            'financialHold' => $item->hasActiveFinancialHold(),
            'courseStartedAt' => $this->iso($detail?->course_started_at),
            'courseEndAt' => $this->iso($detail?->course_end_at),
            'givenCount' => $givenCount,
            'totalAdministrations' => $total !== null ? (int) $total : null,
            'remaining' => $total !== null ? max(0, (int) $total - $givenCount) : null,
            'nextDueAt' => $nextDue?->dueAt->toIso8601String(),
            'nextDueLabel' => $nextDue?->dueAt->format('D H:i'),
            'nextDueSequence' => $nextDue?->sequence,
            'nextDueStatus' => $nextDueSlot['status'] ?? null,
            'canRecord' => $canRecord,
            'slots' => $slots,
            'unslottedAdministrations' => $unslotted,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function administrationPayload(MedicationAdministration $administration): array
    {
        $startedAt = Carbon::parse($administration->started_at);
        $status = $administration->status instanceof MedicationAdministrationStatus
            ? $administration->status
            : enum_try_from(MedicationAdministrationStatus::class, (string) $administration->status);

        return [
            'id' => $administration->id,
            'status' => enum_value($status) ?? MedicationSlotStatus::GIVEN->value,
            'statusLabel' => $status?->getLabel() ?? 'Given',
            'startedAt' => $startedAt->toIso8601String(),
            'startedAtLabel' => $startedAt->format('H:i'),
            'by' => $administration->administeredBy?->name,
            'quantity' => $administration->quantity_given,
            'unit' => $administration->doseUnit?->code ?? $administration->doseUnit?->label,
            'reason' => $administration->omission_reason ?: $administration->prn_reason,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array{start: string, end: string}
     */
    protected function window(array $nodes, CarbonInterface $now): array
    {
        $start = $now->copy()->subHours(6);
        $end = $now->copy()->addHours(12);

        foreach ($nodes as $node) {
            foreach ([$node['courseStartedAt'], ...array_column($node['unslottedAdministrations'], 'startedAt')] as $candidate) {
                if ($candidate && ($parsed = Carbon::parse($candidate))->lt($start)) {
                    $start = $parsed;
                }
            }

            $lastSlot = $node['slots'] !== [] ? end($node['slots'])['dueAt'] : null;

            foreach ([$node['courseEndAt'], $lastSlot] as $candidate) {
                if ($candidate && ($parsed = Carbon::parse($candidate))->gt($end)) {
                    $end = $parsed;
                }
            }
        }

        return [
            'start' => $start->copy()->startOfHour()->toIso8601String(),
            'end' => $end->copy()->addHour()->startOfHour()->toIso8601String(),
        ];
    }

    protected function doseLabel(?object $detail): ?string
    {
        if ($detail === null) {
            return null;
        }

        if ($detail->dose_amount !== null) {
            $amount = rtrim(rtrim(number_format((float) $detail->dose_amount, 4, '.', ''), '0'), '.');
            $unit = $detail->doseUnit?->code ?? $detail->doseUnit?->label;

            return trim($amount.' '.($unit ?? ''));
        }

        return $detail->dosage ?: null;
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) enum_value($value);
    }

    /**
     * Resolves a human label through the Pharmacy enum when the module is
     * present, without hard-importing it.
     */
    protected function enumLabel(string $enumClass, mixed $value): ?string
    {
        $raw = $this->enumValue($value);

        if ($raw === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'getLabel')) {
            return (string) $value->getLabel();
        }

        $label = OptionalClass::when(
            $enumClass,
            fn (string $class): ?string => enum_try_from($class, $raw)?->getLabel(),
            'Pharmacy',
        );

        return $label ?? strtoupper($raw);
    }

    protected function iso(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
