<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanRoutineCare;

class CarePlanRoutineCareFactory extends Factory
{
    protected $model = CarePlanRoutineCare::class;

    public function definition(): array
    {
        return [
            'care_plan_id' => CarePlan::factory(),
            'item' => fake()->randomElement(RoutineCareItem::cases()),
            'specification' => fake()->sentence(3),
            'not_applicable' => false,
            'notes' => fake()->optional()->sentence(),
            'specified_by' => User::factory(),
            'specified_at' => now(),
        ];
    }
}
