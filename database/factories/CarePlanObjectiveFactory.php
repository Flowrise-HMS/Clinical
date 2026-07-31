<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalLifecycleStatus;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanObjective;

class CarePlanObjectiveFactory extends Factory
{
    protected $model = CarePlanObjective::class;

    public function definition(): array
    {
        return [
            'care_plan_diagnosis_id' => CarePlanDiagnosis::factory(),
            'description' => fake()->sentence(5),
            'target_measure' => fake()->optional()->word(),
            'target_value' => fake()->optional()->word(),
            'target_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'lifecycle_status' => GoalLifecycleStatus::PROPOSED,
            'achievement_status' => GoalAchievementStatus::IN_PROGRESS,
            'start_date' => now()->toDateString(),
            'author_id' => User::factory(),
        ];
    }
}
