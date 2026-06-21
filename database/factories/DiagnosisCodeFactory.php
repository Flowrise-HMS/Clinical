<?php

namespace Modules\Clinical\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\DiagnosisCode;

class DiagnosisCodeFactory extends Factory
{
    protected $model = DiagnosisCode::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('??.###')),
            'description' => fake()->sentence(4),
            'category' => fake()->randomElement(['infectious', 'neoplasm', 'endocrine', 'mental', 'nervous', 'circulatory', 'respiratory', 'digestive']),
            'nhis_covered' => fake()->boolean(70),
            'source' => fake()->randomElement(['icd-11', 'icd-10', 'local']),
            'is_active' => true,
        ];
    }
}
