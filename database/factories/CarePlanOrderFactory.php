<?php

namespace Modules\Clinical\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\CarePlanOrderStatus;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;

class CarePlanOrderFactory extends Factory
{
    protected $model = CarePlanOrder::class;

    public function definition(): array
    {
        return [
            'care_plan_diagnosis_id' => CarePlanDiagnosis::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 1000),
            'instruction' => fake()->sentence(6),
            'frequency' => fake()->randomElement(['once', 'daily', 'twice daily', 'every 4 hours']),
            'status' => CarePlanOrderStatus::PLANNED,
            'plannable_type' => null,
            'plannable_id' => null,
        ];
    }
}
