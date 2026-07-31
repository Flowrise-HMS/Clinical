<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class CarePlanDiagnosisFactory extends Factory
{
    protected $model = CarePlanDiagnosis::class;

    public function definition(): array
    {
        $problemStatement = fake()->sentence(3);
        $relatedTo = fake()->sentence(3);
        $asEvidencedBy = fake()->sentence(3);

        return [
            'care_plan_id' => CarePlan::factory(),
            'care_plan_problem_id' => CarePlanProblem::factory(),
            'catalogue_id' => NursingDiagnosisCatalogue::factory(),
            'problem_statement' => $problemStatement,
            'related_to' => $relatedTo,
            'as_evidenced_by' => $asEvidencedBy,
            'composed_statement' => CarePlanDiagnosis::composePes(
                $problemStatement,
                $relatedTo,
                $asEvidencedBy,
            ),
            'recorded_at' => now(),
            'formulated_by' => User::factory(),
        ];
    }
}
