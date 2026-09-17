<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\DischargeCondition;
use Modules\Clinical\Enums\DischargeSummaryStatus;
use Modules\Clinical\Models\DischargeSummary;
use Modules\Clinical\Models\Encounter;

/**
 * @extends Factory<DischargeSummary>
 */
class DischargeSummaryFactory extends Factory
{
    protected $model = DischargeSummary::class;

    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory()->inpatient(),
            'patient_id' => fn (array $attributes) => Encounter::query()->find($attributes['encounter_id'])?->patient_id,
            'branch_id' => fn (array $attributes) => Encounter::query()->find($attributes['encounter_id'])?->branch_id,
            'admission_diagnosis' => $this->faker->sentence(3),
            'discharge_diagnoses' => [['description' => $this->faker->sentence(3), 'icd10_code' => null, 'type' => 'primary', 'certainty' => 'confirmed']],
            'presenting_complaint' => $this->faker->sentence(),
            'hospital_course' => $this->faker->paragraph(),
            'condition_at_discharge' => DischargeCondition::IMPROVED,
            'discharge_medications' => [],
            'instructions' => $this->faker->sentence(),
            'status' => DischargeSummaryStatus::DRAFT,
            'authored_by' => User::factory(),
        ];
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'status' => DischargeSummaryStatus::SIGNED,
            'signed_by' => User::factory(),
            'signed_at' => now(),
        ]);
    }
}
