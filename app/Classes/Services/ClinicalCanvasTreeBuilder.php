<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ClinicalNotes\ClinicalNoteResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\EncounterResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\ServiceRequests\ServiceRequestResource;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\VitalSigns\VitalSignResource;
use Modules\Clinical\Models\ClinicalNote;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Clinical\Models\EncounterLocationEvent;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Clinical\Models\VitalSign;
use Modules\Core\Support\ModuleAvailability;
use Modules\Patient\Models\Patient;

/**
 * Builds the hierarchical node tree the clinical canvas renders:
 * Patient → Encounters → activity groups → items (orders → request items,
 * medications → dose slots). Every node carries only display data; the
 * canvas engine decides positions.
 */
class ClinicalCanvasTreeBuilder
{
    public const ENCOUNTER_LIMIT = 25;

    public const GROUP_ITEM_LIMIT = 40;

    public function __construct(
        protected MedicationCanvasService $medicationCanvasService,
        protected ClinicalWorkspaceService $workspaceService,
    ) {}

    /**
     * @return array{root: array<string, mixed>, meta: array<string, mixed>}
     */
    public function buildPatientTree(Patient $patient, ?User $user = null): array
    {
        $now = now();

        $encounters = Encounter::query()
            ->where('patient_id', $patient->id)
            ->with(['location', 'bed', 'admittedBy'])
            ->orderByDesc('admitted_at')
            ->orderByDesc('created_at')
            ->limit(self::ENCOUNTER_LIMIT)
            ->get();

        $encounterIds = $encounters->pluck('id')->all();

        $vitals = VitalSign::query()->where('patient_id', $patient->id)->with('recordedBy')->orderByDesc('recorded_at')->get();
        $notes = ClinicalNote::query()->where('patient_id', $patient->id)->with('author')->orderByDesc('created_at')->get();
        $diagnoses = EncounterDiagnosis::query()->whereIn('encounter_id', $encounterIds)->with('diagnosisCode')->orderByDesc('created_at')->get();
        $orders = ServiceRequest::query()->where('patient_id', $patient->id)->with(['orderedBy', 'items.service'])->orderByDesc('created_at')->get();
        $adtEvents = EncounterLocationEvent::query()->whereIn('encounter_id', $encounterIds)->with(['toLocation', 'toBed'])->orderByDesc('occurred_at')->get();

        $medicationsByEncounter = [];

        if ($this->medicationCanvasService->isAvailable()) {
            foreach ($encounters as $encounter) {
                $medicationsByEncounter[$encounter->id] = $this->medicationCanvasService->buildNodes($encounter, $user)['nodes'];
            }
        }

        $children = [];

        $patient->loadMissing('allergies');

        if ($patient->allergies->isNotEmpty()) {
            $children[] = $this->group('patient_allergies', 'allergy', 'Allergies', $patient->allergies->map(fn ($allergy): array => [
                'id' => 'allergy_'.$allergy->id,
                'kind' => 'item',
                'type' => 'allergy',
                'title' => (string) $allergy->allergen,
                'subtitle' => $this->enumLabel($allergy->severity),
                'isCritical' => in_array($this->enumValue($allergy->severity), ['severe', 'life_threatening'], true),
                'metadata' => array_filter([
                    'Reaction' => $allergy->reaction,
                    'Type' => $this->enumLabel($allergy->allergen_type),
                    'Status' => $this->enumLabel($allergy->verification_status),
                ]),
            ])->all());
        }

        $first = true;

        foreach ($encounters as $encounter) {
            $children[] = $this->encounterNode(
                $encounter,
                $vitals->where('encounter_id', $encounter->id),
                $notes->where('encounter_id', $encounter->id),
                $diagnoses->where('encounter_id', $encounter->id),
                $orders->where('encounter_id', $encounter->id),
                $adtEvents->where('encounter_id', $encounter->id),
                $medicationsByEncounter[$encounter->id] ?? [],
                defaultCollapsed: ! $first,
            );
            $first = false;
        }

        $unlinkedVitals = $vitals->whereNull('encounter_id');
        $unlinkedNotes = $notes->whereNull('encounter_id');
        $unlinkedOrders = $orders->whereNull('encounter_id');

        if ($unlinkedVitals->isNotEmpty() || $unlinkedNotes->isNotEmpty() || $unlinkedOrders->isNotEmpty()) {
            $children[] = [
                'id' => 'unlinked',
                'kind' => 'group',
                'type' => 'other',
                'title' => 'Outside an encounter',
                'count' => $unlinkedVitals->count() + $unlinkedNotes->count() + $unlinkedOrders->count(),
                'defaultCollapsed' => true,
                'children' => array_values(array_filter([
                    $this->vitalsGroup('unlinked', $unlinkedVitals),
                    $this->notesGroup('unlinked', $unlinkedNotes),
                    $this->ordersGroup('unlinked', $unlinkedOrders, []),
                ])),
            ];
        }

        if (ModuleAvailability::appointmentEnabled()) {
            $appointments = $this->workspaceService
                ->setPatient($patient)
                ->clearEncounter()
                ->getTimelineEvents(self::GROUP_ITEM_LIMIT, 'appointment')
                ->map(fn (array $event): array => $this->eventItem($event))
                ->all();

            if ($appointments !== []) {
                $children[] = $this->group('patient_appointments', 'appointment', 'Appointments', $appointments, defaultCollapsed: true);
            }
        }

        $encounterCount = Encounter::query()->where('patient_id', $patient->id)->count();

        return [
            'root' => [
                'id' => 'patient_'.$patient->id,
                'kind' => 'patient',
                'title' => (string) $patient->full_name,
                'subtitle' => implode(' · ', array_filter([
                    $patient->mrn ? 'MRN '.$patient->mrn : null,
                    $patient->age !== null ? $patient->age.' yrs' : null,
                    $this->enumLabel($patient->gender),
                ])),
                'badges' => $patient->allergies->isNotEmpty()
                    ? [['label' => $patient->allergies->count().' allergies', 'color' => 'danger']]
                    : [],
                'count' => $encounterCount,
                'children' => $children,
            ],
            'meta' => [
                'now' => $now->toIso8601String(),
                'timezone' => config('app.timezone'),
                'encounterTotal' => $encounterCount,
                'encounterLimit' => self::ENCOUNTER_LIMIT,
                'truncated' => $encounterCount > self::ENCOUNTER_LIMIT,
            ],
        ];
    }

    /**
     * The medication canvas is the same tree with the active encounter as root.
     *
     * @return array{root: array<string, mixed>, meta: array<string, mixed>}
     */
    public function buildEncounterMedicationTree(Encounter $encounter, ?User $user = null): array
    {
        ['nodes' => $nodes, 'meta' => $meta] = $this->medicationCanvasService->buildNodes($encounter, $user);

        $encounter->loadMissing(['location', 'bed']);

        return [
            'root' => [
                ...$this->encounterSummary($encounter),
                'count' => count($nodes),
                'children' => array_map(fn (array $node): array => $this->medicationTreeNode($node), $nodes),
            ],
            'meta' => $meta,
        ];
    }

    /**
     * @param  Collection<int, VitalSign>  $vitals
     * @param  Collection<int, ClinicalNote>  $notes
     * @param  Collection<int, EncounterDiagnosis>  $diagnoses
     * @param  Collection<int, ServiceRequest>  $orders
     * @param  Collection<int, EncounterLocationEvent>  $adtEvents
     * @param  list<array<string, mixed>>  $medications
     * @return array<string, mixed>
     */
    protected function encounterNode(
        Encounter $encounter,
        Collection $vitals,
        Collection $notes,
        Collection $diagnoses,
        Collection $orders,
        Collection $adtEvents,
        array $medications,
        bool $defaultCollapsed,
    ): array {
        $medicationItemIds = array_column($medications, 'requestItemId');
        $prefix = 'enc_'.$encounter->id;

        $groups = array_values(array_filter([
            $this->vitalsGroup($prefix, $vitals),
            $diagnoses->isNotEmpty() ? $this->group($prefix.'_diagnoses', 'diagnosis', 'Diagnoses', $diagnoses->map(fn (EncounterDiagnosis $diagnosis): array => [
                'id' => 'diagnosis_'.$diagnosis->id,
                'kind' => 'item',
                'type' => 'diagnosis',
                'title' => (string) ($diagnosis->description ?: $diagnosis->diagnosisCode?->description ?: 'Diagnosis'),
                'subtitle' => implode(' · ', array_filter([$diagnosis->icd10_code ?: $diagnosis->icd_code, $this->enumLabel($diagnosis->type)])),
                'timeLabel' => $this->timeLabel($diagnosis->created_at),
                'occurredAt' => $this->iso($diagnosis->created_at),
                'metadata' => array_filter([
                    'Certainty' => $this->enumLabel($diagnosis->certainty),
                    'New case' => $diagnosis->is_new_case ? 'Yes' : null,
                    'Notes' => $diagnosis->notes,
                ]),
            ])->all()) : null,
            $this->notesGroup($prefix, $notes),
            $this->ordersGroup($prefix, $orders, $medicationItemIds),
            $medications !== [] ? $this->group($prefix.'_medications', 'medication', 'Medications', array_map(fn (array $node): array => $this->medicationTreeNode($node), $medications)) : null,
            $adtEvents->isNotEmpty() ? $this->group($prefix.'_adt', 'adt', 'Admission & transfers', $adtEvents->map(fn (EncounterLocationEvent $event): array => [
                'id' => 'adt_'.$event->id,
                'kind' => 'item',
                'type' => 'adt',
                'title' => $this->enumLabel($event->event_type) ?? 'Location event',
                'subtitle' => implode(' → ', array_filter([$event->toLocation?->name, $event->toBed?->name, $event->destination_label])),
                'timeLabel' => $this->timeLabel($event->occurred_at),
                'occurredAt' => $this->iso($event->occurred_at),
                'metadata' => array_filter(['Notes' => $event->notes]),
            ])->all(), defaultCollapsed: true) : null,
        ]));

        return [
            ...$this->encounterSummary($encounter),
            'count' => array_sum(array_column($groups, 'count')),
            'defaultCollapsed' => $defaultCollapsed,
            'children' => $groups,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function encounterSummary(Encounter $encounter): array
    {
        $status = $this->enumValue($encounter->status);

        return [
            'id' => 'encounter_'.$encounter->id,
            'kind' => 'encounter',
            'title' => trim(($this->enumLabel($encounter->type) ?? 'Encounter').' '.$encounter->encounter_number),
            'subtitle' => implode(' → ', array_filter([
                $this->dateLabel($encounter->admitted_at ?? $encounter->created_at),
                $encounter->discharged_at ? $this->dateLabel($encounter->discharged_at) : ($encounter->isActive() ? 'ongoing' : null),
            ])),
            'isActive' => $encounter->isActive(),
            'badges' => array_values(array_filter([
                $status ? ['label' => $this->enumLabel($encounter->status) ?? $status, 'color' => $this->enumColor($encounter->status) ?? 'gray'] : null,
                $encounter->location?->name ? ['label' => $encounter->location->name.($encounter->bed?->name ? ' · '.$encounter->bed->name : ''), 'color' => 'gray'] : null,
            ])),
            'metadata' => array_filter([
                'Chief complaint' => $encounter->chief_complaint,
                'Length of stay' => $encounter->duration,
                'Admitted by' => $encounter->admittedBy?->name,
                'Disposition' => $this->enumLabel($encounter->discharge_disposition),
            ]),
            'url' => EncounterResource::getUrl('view', ['record' => $encounter]),
        ];
    }

    /**
     * @param  Collection<int, VitalSign>  $vitals
     * @return array<string, mixed>|null
     */
    protected function vitalsGroup(string $prefix, Collection $vitals): ?array
    {
        if ($vitals->isEmpty()) {
            return null;
        }

        return $this->group($prefix.'_vitals', 'vitals', 'Vitals', $vitals->map(fn (VitalSign $vital): array => [
            'id' => 'vitals_'.$vital->id,
            'kind' => 'item',
            'type' => 'vitals',
            'title' => implode('  ', array_filter([
                $vital->blood_pressure ? 'BP '.$vital->blood_pressure : null,
                $vital->heart_rate ? 'HR '.$vital->heart_rate : null,
                $vital->temperature ? 'T '.$vital->temperature.'°' : null,
                $vital->spo2 ? 'SpO₂ '.$vital->spo2.'%' : null,
            ])) ?: 'Vital signs',
            'subtitle' => $vital->recordedBy?->name,
            'timeLabel' => $this->timeLabel($vital->recorded_at),
            'occurredAt' => $this->iso($vital->recorded_at),
            'isCritical' => method_exists($vital, 'isCritical') ? (bool) $vital->isCritical() : false,
            'metadata' => array_filter([
                'Respiratory rate' => $vital->respiratory_rate,
                'Weight' => $vital->weight ? $vital->weight.' kg' : null,
                'Height' => $vital->height ? $vital->height.' cm' : null,
                'BMI' => $vital->bmi,
                'Pain level' => $vital->pain_level,
            ]),
            'url' => VitalSignResource::getUrl('view', ['record' => $vital]),
        ])->all());
    }

    /**
     * @param  Collection<int, ClinicalNote>  $notes
     * @return array<string, mixed>|null
     */
    protected function notesGroup(string $prefix, Collection $notes): ?array
    {
        if ($notes->isEmpty()) {
            return null;
        }

        return $this->group($prefix.'_notes', 'note', 'Notes', $notes->map(fn (ClinicalNote $note): array => [
            'id' => 'note_'.$note->id,
            'kind' => 'item',
            'type' => 'note',
            'title' => (string) ($note->subject ?: ($this->enumLabel($note->note_type) ?? 'Clinical note')),
            'subtitle' => implode(' · ', array_filter([$this->enumLabel($note->note_type), $note->author?->name])),
            'timeLabel' => $this->timeLabel($note->created_at),
            'occurredAt' => $this->iso($note->created_at),
            'description' => str(strip_tags(is_array($note->content) ? (string) ($note->content['text'] ?? $note->content['summary'] ?? '') : (string) $note->content))->squish()->limit(240)->toString(),
            'badges' => array_values(array_filter([
                $note->status ? ['label' => $this->enumLabel($note->status) ?? (string) $this->enumValue($note->status), 'color' => $this->enumColor($note->status) ?? 'gray'] : null,
            ])),
            'url' => ClinicalNoteResource::getUrl('view', ['record' => $note]),
        ])->all());
    }

    /**
     * @param  Collection<int, ServiceRequest>  $orders
     * @param  list<string>  $medicationItemIds
     * @return array<string, mixed>|null
     */
    protected function ordersGroup(string $prefix, Collection $orders, array $medicationItemIds): ?array
    {
        $orderNodes = [];

        foreach ($orders as $order) {
            $items = $order->items->reject(fn (RequestItem $item): bool => in_array($item->id, $medicationItemIds, true));

            if ($items->isEmpty()) {
                continue;
            }

            $orderNodes[] = [
                'id' => 'order_'.$order->id,
                'kind' => 'item',
                'type' => 'order',
                'title' => (string) ($order->request_number ?: 'Order'),
                'subtitle' => implode(' · ', array_filter([
                    $items->count().' '.str('item')->plural($items->count()),
                    $this->enumLabel($order->priority),
                    $order->orderedBy?->name,
                ])),
                'timeLabel' => $this->timeLabel($order->created_at),
                'occurredAt' => $this->iso($order->created_at),
                'badges' => array_values(array_filter([
                    $order->status ? ['label' => $this->enumLabel($order->status) ?? (string) $this->enumValue($order->status), 'color' => $this->enumColor($order->status) ?? 'gray'] : null,
                ])),
                'metadata' => array_filter(['Notes' => $order->notes]),
                'url' => ServiceRequestResource::getUrl('view', ['record' => $order]),
                'defaultCollapsed' => true,
                'children' => $items->map(fn (RequestItem $item): array => [
                    'id' => 'request_item_'.$item->id,
                    'kind' => 'item',
                    'type' => 'request_item',
                    'title' => (string) ($item->service?->name ?? 'Service'),
                    'subtitle' => $item->quantity > 1 ? '× '.$item->quantity : null,
                    'badges' => array_values(array_filter([
                        $item->status ? ['label' => $this->enumLabel($item->status) ?? (string) $this->enumValue($item->status), 'color' => $this->enumColor($item->status) ?? 'gray'] : null,
                    ])),
                    'metadata' => array_filter([
                        'Fulfilled' => $item->fulfilled_at ? $this->timeLabel($item->fulfilled_at) : null,
                        'Notes' => $item->notes,
                    ]),
                ])->values()->all(),
            ];
        }

        if ($orderNodes === []) {
            return null;
        }

        return $this->group($prefix.'_orders', 'order', 'Orders', $orderNodes);
    }

    /**
     * Converts a MedicationCanvasService node into a tree node whose children
     * are its dose slots (laid out as a grid by the engine).
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function medicationTreeNode(array $node): array
    {
        $doses = array_map(fn (array $slot): array => [
            'id' => $node['id'].'_dose_'.$slot['sequence'],
            'kind' => 'dose',
            'type' => 'medication',
            'requestItemId' => $node['requestItemId'],
            'title' => $slot['dueAtLabel'],
            'subtitle' => '#'.$slot['sequence'],
            'sequence' => $slot['sequence'],
            'dueAt' => $slot['dueAt'],
            'dueAtLabel' => $slot['dueAtLabel'],
            'status' => $slot['status'],
            'actionable' => $slot['actionable'],
            'isNextDue' => $slot['sequence'] === ($node['nextDueSequence'] ?? null),
            'administration' => $slot['administration'],
        ], $node['slots']);

        foreach ($node['unslottedAdministrations'] as $administration) {
            $doses[] = [
                'id' => $node['id'].'_admin_'.$administration['id'],
                'kind' => 'dose',
                'type' => 'medication',
                'requestItemId' => $node['requestItemId'],
                'title' => $administration['startedAtLabel'],
                'subtitle' => 'PRN',
                'sequence' => null,
                'dueAt' => $administration['startedAt'],
                'dueAtLabel' => $administration['startedAtLabel'],
                'status' => $administration['status'],
                'actionable' => false,
                'isNextDue' => false,
                'administration' => $administration,
            ];
        }

        return [
            ...$node,
            'kind' => 'medication',
            'type' => 'medication',
            'title' => $node['name'],
            'subtitle' => implode(' · ', array_filter([$node['doseLabel'], $node['routeLabel'], $node['frequencyLabel']])),
            'count' => count($doses),
            'childLayout' => 'grid',
            'children' => $doses,
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    protected function eventItem(array $event): array
    {
        $occurredAt = $event['occurred_at'] instanceof CarbonInterface ? $event['occurred_at'] : Carbon::parse($event['occurred_at']);

        return [
            'id' => (string) $event['id'],
            'kind' => 'item',
            'type' => (string) $event['type'],
            'title' => (string) $event['title'],
            'subtitle' => null,
            'description' => (string) ($event['description'] ?? ''),
            'timeLabel' => $this->timeLabel($occurredAt),
            'occurredAt' => $occurredAt->toIso8601String(),
            'isCritical' => (bool) ($event['is_critical'] ?? false),
            'metadata' => array_filter($event['metadata'] ?? [], fn ($value): bool => filled($value)),
            'url' => $event['url'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function group(string $id, string $type, string $title, array $children, bool $defaultCollapsed = false): array
    {
        $total = count($children);
        $children = array_slice(array_values($children), 0, self::GROUP_ITEM_LIMIT);

        return [
            'id' => $id,
            'kind' => 'group',
            'type' => $type,
            'title' => $title,
            'count' => $total,
            'truncated' => $total > count($children),
            'defaultCollapsed' => $defaultCollapsed,
            'children' => $children,
        ];
    }

    protected function timeLabel(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::parse($value)->format('D j M, H:i');
    }

    protected function dateLabel(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::parse($value)->format('j M Y');
    }

    protected function iso(mixed $value): ?string
    {
        return blank($value) ? null : Carbon::parse($value)->toIso8601String();
    }

    protected function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) enum_value($value);
    }

    protected function enumLabel(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value) && method_exists($value, 'getLabel')) {
            return (string) $value->getLabel();
        }

        return str((string) $this->enumValue($value))->replace('_', ' ')->title()->toString();
    }

    protected function enumColor(mixed $value): ?string
    {
        if (is_object($value) && method_exists($value, 'getColor')) {
            $color = $value->getColor();

            return is_string($color) ? $color : null;
        }

        return null;
    }
}
