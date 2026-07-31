<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class NursingDiagnosisService
{
    public function formulate(
        CarePlanProblem $problem,
        NursingDiagnosisCatalogue $catalogue,
        string $problemStatement,
        string $relatedTo,
        string $asEvidencedBy,
        User $formulatedBy,
    ): CarePlanDiagnosis {
        return DB::transaction(function () use ($problem, $catalogue, $problemStatement, $relatedTo, $asEvidencedBy, $formulatedBy): CarePlanDiagnosis {
            $problem = CarePlanProblem::query()
                ->with('carePlan')
                ->lockForUpdate()
                ->findOrFail($problem->id);

            $this->assertPlanIsOpen($problem->carePlan);

            $catalogue = NursingDiagnosisCatalogue::query()
                ->lockForUpdate()
                ->findOrFail($catalogue->id);

            if (! $catalogue->is_active) {
                throw new \InvalidArgumentException(__('An active nursing diagnosis catalogue entry is required.'));
            }

            return $problem->diagnoses()->create([
                'care_plan_id' => $problem->care_plan_id,
                'catalogue_id' => $catalogue->id,
                'problem_statement' => $problemStatement,
                'related_to' => $relatedTo,
                'as_evidenced_by' => $asEvidencedBy,
                'composed_statement' => CarePlanDiagnosis::composePes(
                    $problemStatement,
                    $relatedTo,
                    $asEvidencedBy,
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
