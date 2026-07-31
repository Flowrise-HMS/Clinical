<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Clinical\Models\CarePlanEvaluation;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanEvaluationFactory extends Factory
{
    protected $model = CarePlanEvaluation::class;

    public function definition(): array
    {
        return [
            'care_plan_objective_id' => CarePlanObjective::factory(),
            'evaluated_by' => User::factory(),
            'evaluated_at' => now(),
            'outcome' => GoalEvaluationOutcome::PARTIALLY_MET,
            'findings' => fake()->paragraph(),
            'next_action' => GoalEvaluationNextAction::CONTINUE,
            'achievement_status_snapshot' => GoalAchievementStatus::IMPROVING,
        ];
    }
}
