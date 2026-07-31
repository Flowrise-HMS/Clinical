<?php

namespace Modules\Clinical\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;

class NursingDiagnosisCatalogueFactory extends Factory
{
    protected $model = NursingDiagnosisCatalogue::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('NANDA-###??')),
            'label' => fake()->sentence(3),
            'definition' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
