<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\AllergyService;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Models\Allergy;

/**
 * @group Clinical
 *
 * Patient allergies and adverse reactions (branch isolation through the patient).
 * Deleting archives the record.
 */
class AllergyController extends ClinicalApiController
{
    protected string $branchScope = 'via:patient';

    public function __construct(protected AllergyService $allergies) {}

    public static function model(): string
    {
        return Allergy::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'allergen', 'allergen_code', 'allergen_type', 'reaction', 'severity', 'onset_type', 'onset_date',
            'is_active', 'verification_status', 'verified_at', 'notes', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'severity' => 'severity', 'is_active' => 'is_active'];
    }

    public function searchable(): array
    {
        return ['allergen'];
    }

    public function storeRules(): array
    {
        return [
            'patient_id' => ['required', 'uuid'],
            'allergen' => ['required', 'string', 'max:255'],
            'allergen_type' => ['nullable', Rule::enum(AllergenType::class)],
            'reaction' => ['nullable', 'string', 'max:1000'],
            'severity' => ['required', Rule::enum(AllergySeverity::class)],
            'onset_type' => ['nullable', Rule::enum(OnsetType::class)],
            'onset_date' => ['nullable', 'date', 'before_or_equal:today'],
            'verification_status' => ['nullable', Rule::enum(AllergyVerificationStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return collect(parent::updateRules($record))
            ->except('patient_id')
            ->put('is_active', ['sometimes', 'boolean'])
            ->all();
    }

    protected function createRecord(array $data): Model
    {
        // AllergyService writes explicit nulls, which bypass the column defaults.
        $data['onset_type'] ??= OnsetType::UNKNOWN->value;

        return $this->allergies->record($this->patient($data['patient_id']), $data);
    }
}
