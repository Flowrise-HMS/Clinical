<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\DiagnosisCode;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\EncounterDiagnosis;
use Modules\Patient\Models\Patient;

class EncounterDiagnosisFactory extends Factory
{
    protected $model = EncounterDiagnosis::class;

    public function definition(): array
    {
        return [
            'encounter_id' => Encounter::factory(),
            'patient_id' => Patient::factory(),
            'diagnosis_code_id' => DiagnosisCode::factory(),
            'icd_code' => strtoupper(fake()->bothify('???.###')),
            'description' => fake()->sentence(4),
            'type' => fake()->randomElement(['primary', 'secondary', 'complication']),
            'ordered_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
