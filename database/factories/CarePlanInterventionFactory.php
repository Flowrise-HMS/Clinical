<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\CarePlanIntervention;
use Modules\Clinical\Models\CarePlanOrder;

class CarePlanInterventionFactory extends Factory
{
    protected $model = CarePlanIntervention::class;

    public function definition(): array
    {
        return [
            'care_plan_order_id' => CarePlanOrder::factory(),
            'description' => fake()->sentence(6),
            'performed_at' => now(),
            'performed_by' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
