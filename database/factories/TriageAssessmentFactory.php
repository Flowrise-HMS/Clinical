<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\TriageMobility;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\TriageAssessment;

/**
 * @extends Factory<TriageAssessment>
 */
class TriageAssessmentFactory extends Factory
{
    protected $model = TriageAssessment::class;

    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory(),
            'patient_id' => fn (array $attributes) => Encounter::query()->find($attributes['encounter_id'])?->patient_id,
            'branch_id' => fn (array $attributes) => Encounter::query()->find($attributes['encounter_id'])?->branch_id,
            'age_band' => TriageAgeBand::ADULT,
            'mobility' => TriageMobility::WALKING,
            'avpu' => AvpuLevel::ALERT,
            'trauma' => false,
            'tews_score' => 1,
            'tews_breakdown' => null,
            'discriminators' => [],
            'tews_category' => TriageCategory::GREEN,
            'discriminator_category' => null,
            'final_category' => TriageCategory::GREEN,
            'disposition' => TriageDisposition::CONSULTATION,
            'triaged_by' => User::factory(),
            'triaged_at' => now(),
        ];
    }

    public function category(TriageCategory $category): static
    {
        return $this->state(fn (): array => [
            'tews_category' => $category,
            'final_category' => $category,
        ]);
    }
}
