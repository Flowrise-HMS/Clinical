<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\NursingProblemStatus;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanProblem;

class CarePlanProblemFactory extends Factory
{
    protected $model = CarePlanProblem::class;

    public function definition(): array
    {
        return [
            'care_plan_id' => CarePlan::factory(),
            'label' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => NursingProblemStatus::ACTIVE,
            'priority' => fake()->numberBetween(1, 5),
            'identified_by' => User::factory(),
        ];
    }
}
