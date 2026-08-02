<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class NursingDiagnosisService
{
    public function formulate(
        CarePlanProblem $problem,
        ?NursingDiagnosisCatalogue $catalogue,
        ?string $problemStatement,
        ?string $relatedTo,
        ?string $asEvidencedBy,
        User $formulatedBy,
        ?string $label = null,
        bool $saveToCatalogue = false,
    ): CarePlanDiagnosis {
        return DB::transaction(function () use (
            $problem,
            $catalogue,
            $problemStatement,
            $relatedTo,
            $asEvidencedBy,
            $formulatedBy,
            $label,
            $saveToCatalogue,
        ): CarePlanDiagnosis {
            $problem = CarePlanProblem::query()
                ->with('carePlan')
                ->lockForUpdate()
                ->findOrFail($problem->id);

            $this->assertPlanIsOpen($problem->carePlan);

            if ($catalogue !== null) {
                $catalogue = NursingDiagnosisCatalogue::query()
                    ->lockForUpdate()
                    ->findOrFail($catalogue->id);

                if (! $catalogue->is_active) {
                    throw new \InvalidArgumentException(__('An active nursing diagnosis catalogue entry is required.'));
                }
            } elseif (filled($label) && $saveToCatalogue) {
                $catalogue = NursingDiagnosisCatalogue::query()->create([
                    'label' => $label,
                    'code' => 'CUSTOM-'.strtoupper(substr(str_replace('-', '', (string) Str::uuid()), 0, 10)),
                    'definition' => $label,
                    'is_active' => true,
                ]);
            }

            $fallbackLabel = $catalogue?->label ?? $label;

            if (blank($fallbackLabel) && blank($problemStatement)) {
                throw new \InvalidArgumentException(__('A nursing diagnosis label or problem statement is required.'));
            }

            return $problem->diagnoses()->create([
                'care_plan_id' => $problem->care_plan_id,
                'catalogue_id' => $catalogue?->id,
                'label' => $fallbackLabel,
                'problem_statement' => $problemStatement,
                'related_to' => $relatedTo,
                'as_evidenced_by' => $asEvidencedBy,
                'composed_statement' => CarePlanDiagnosis::composePes(
                    $problemStatement,
                    $relatedTo,
                    $asEvidencedBy,
                    $fallbackLabel,
                ),
                'recorded_at' => now(),
                'formulated_by' => $formulatedBy->id,
            ]);
        });
    }

    protected function assertPlanIsOpen(CarePlan $plan): void
    {
        if (! $plan->status->isOpen()) {
            throw new \InvalidArgumentException(__('Only open care plans can be updated.'));
        }
    }
}
