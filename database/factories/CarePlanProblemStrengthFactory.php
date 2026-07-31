<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\CarePlanProblemStrength;

class CarePlanProblemStrengthFactory extends Factory
{
    protected $model = CarePlanProblemStrength::class;

    public function definition(): array
    {
        return [
            'care_plan_problem_id' => CarePlanProblem::factory(),
            'description' => fake()->sentence(),
            'identified_by' => User::factory(),
            'identified_at' => now(),
        ];
    }
}
