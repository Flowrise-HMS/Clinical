<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;

/**
 * @group Clinical
 *
 * Medication administration record (MAR) entries. Recording goes through the MAR
 * rules (payment gate, allergy block, witness for controlled drugs); entries are immutable.
 */
class MedicationAdministrationController extends ClinicalApiController
{
    protected string $branchScope = 'via:requestItem.serviceRequest';

    protected string $defaultSort = '-started_at';

    public function __construct(protected MedicationAdministrationService $mar) {}

    public static function model(): string
    {
        return MedicationAdministration::class;
    }

    public function fields(): array
    {
        return ['id', 'request_item_id', 'status', 'quantity_given', 'dose_slot_sequence', 'started_at', 'ended_at', 'administered_by',
            'witness_confirmed', 'omission_reason', 'prn_reason', 'notes', 'created_at'];
    }

    public function filters(): array
    {
        return ['request_item_id' => 'request_item_id', 'status' => 'status'];
    }

    public function sortable(): array
    {
        return ['started_at', 'created_at'];
    }

    public function storeRules(): array
    {
        return [
            'request_item_id' => ['required', 'uuid'],
            'status' => ['required', Rule::enum(MedicationAdministrationStatus::class)],
            'quantity_given' => ['nullable', 'numeric', 'min:0'],
            'witness_confirmed' => ['nullable', 'boolean'],
            'omission_reason' => ['nullable', 'string', 'max:500'],
            'prn_reason' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Reading follows the order (service request) the dose belongs to; recording
     * needs the MAR permission, as in the panel.
     */
    protected function authorizeResource(string $ability, ?Model $record = null): void
    {
        match ($ability) {
            'viewAny' => $this->authorizeApi('viewAny', ServiceRequest::class),
            'view' => $this->authorizeApi('view', $record?->requestItem?->serviceRequest),
            default => $this->requirePermission('administer_medication'),
        };
    }

    protected function createRecord(array $data): Model
    {
        $item = RequestItem::query()->whereHas('serviceRequest')->findOrFail($data['request_item_id']);

        return $this->mar->administer($item, collect($data)->except(['request_item_id', 'notes'])->all(), $data['notes'] ?? null, auth()->user());
    }
}
