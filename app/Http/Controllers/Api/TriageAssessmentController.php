<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Actions\EncounterActions;
use Modules\Clinical\Classes\Services\TriageService;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\TriageMobility;
use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\TriageForm;
use Modules\Clinical\Models\TriageAssessment;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Clinical
 *
 * SATS / TEWS triage assessments. The score and category are computed server-side;
 * vitals sent here are stored as a triage vital-sign reading.
 */
class TriageAssessmentController extends ClinicalApiController
{
    protected string $defaultSort = '-triaged_at';

    public function __construct(protected TriageService $triage) {}

    public static function model(): string
    {
        return TriageAssessment::class;
    }

    public function fields(): array
    {
        return ['id', 'encounter_id', 'patient_id', 'vital_sign_id', 'age_band', 'mobility', 'avpu', 'trauma', 'tews_score',
            'tews_category', 'discriminator_category', 'override_category', 'override_reason', 'final_category', 'disposition',
            'notes', 'triaged_by', 'triaged_at'];
    }

    public function filters(): array
    {
        return ['encounter_id' => 'encounter_id', 'patient_id' => 'patient_id', 'final_category' => 'final_category'];
    }

    public function sortable(): array
    {
        return ['triaged_at', 'created_at'];
    }

    public function storeRules(): array
    {
        return [
            'encounter_id' => ['required', 'uuid'],
            'age_band' => ['nullable', Rule::enum(TriageAgeBand::class)],
            'mobility' => ['nullable', Rule::enum(TriageMobility::class)],
            'avpu' => ['nullable', Rule::enum(AvpuLevel::class)],
            'trauma' => ['nullable', 'boolean'],
            'discriminators' => ['nullable', 'array'],
            'override_category' => ['nullable', Rule::enum(TriageCategory::class)],
            'override_reason' => ['required_with:override_category', 'nullable', 'string', 'max:500'],
            'disposition' => ['nullable', Rule::enum(TriageDisposition::class)],
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'systolic_bp' => ['nullable', 'integer', 'between:40,300'],
            'diastolic_bp' => ['nullable', 'integer', 'between:20,200'],
            'heart_rate' => ['nullable', 'integer', 'between:20,300'],
            'respiratory_rate' => ['nullable', 'integer', 'between:4,80'],
            'temperature' => ['nullable', 'numeric', 'between:25,45'],
            'spo2' => ['nullable', 'integer', 'between:30,100'],
        ];
    }

    /**
     * As in the panel, triage follows the encounter's permissions.
     */
    protected function authorizeResource(string $ability, ?Model $record = null): void
    {
        $this->authorizeThroughEncounter($ability, $record?->encounter);
    }

    protected function createRecord(array $data): Model
    {
        $encounter = $this->encounter($data['encounter_id']);
        $this->authorizeThroughEncounter('update', $encounter);

        if (! EncounterActions::isTriageVisible($encounter)) {
            throw new \DomainException('Only arrived, triaged or in-progress non-inpatient encounters can be triaged.');
        }

        if (filled($data['override_category'] ?? null) && ! TriageForm::canOverride()) {
            throw new AccessDeniedHttpException('You are not allowed to override the triage category.');
        }

        return $this->triage->assess($encounter, $data, auth()->id());
    }
}
