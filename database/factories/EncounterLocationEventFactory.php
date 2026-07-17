<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterLocationEvent;

/**
 * @extends Factory<EncounterLocationEvent>
 */
class EncounterLocationEventFactory extends Factory
{
    protected $model = EncounterLocationEvent::class;

    public function definition(): array
    {
        return [
            'branch_id' => fn (array $attributes) => Encounter::query()
                ->find($attributes['encounter_id'])
                ?->branch_id,
            'encounter_id' => Encounter::factory(),
            'patient_id' => null,
            'event_type' => AdtEventType::Admitted,
            'from_bed_id' => null,
            'to_bed_id' => null,
            'from_location_id' => null,
            'to_location_id' => null,
            'from_department_id' => null,
            'to_department_id' => null,
            'destination_type' => null,
            'destination_branch_id' => null,
            'destination_label' => null,
            'notes' => null,
            'acted_by' => User::factory(),
            'occurred_at' => now(),
        ];
    }
}
