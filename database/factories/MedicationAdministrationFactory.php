<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Modules\Clinical\Models\RequestItem;

class MedicationAdministrationFactory extends Factory
{
    protected $model = MedicationAdministration::class;

    public function definition(): array
    {
        return [
            'request_item_id' => RequestItem::factory(),
            'administered_by' => User::factory(),
            'started_at' => fake()->dateTimeBetween('-2 days', '-1 hour'),
            'ended_at' => fake()->dateTimeBetween('-1 hour', 'now'),
            'quantity_given' => fake()->randomFloat(2, 0.5, 10),
            'status' => MedicationAdministrationStatus::GIVEN,
            'witness_confirmed' => fake()->boolean(80),
            'dose_slot_sequence' => fake()->optional()->numberBetween(1, 10),
        ];
    }
}
