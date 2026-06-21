<?php

namespace Modules\Clinical\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\MedicationDoseReminderLog;
use Modules\Clinical\Models\RequestItem;

class MedicationDoseReminderLogFactory extends Factory
{
    protected $model = MedicationDoseReminderLog::class;

    public function definition(): array
    {
        return [
            'request_item_id' => RequestItem::factory(),
            'dose_slot_sequence' => fake()->numberBetween(1, 10),
            'reminder_type' => fake()->randomElement(['push', 'sms', 'email']),
            'sent_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }
}
