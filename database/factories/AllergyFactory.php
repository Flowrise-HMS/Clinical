<?php

namespace Modules\Clinical\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\OnsetType;
use Modules\Clinical\Models\Allergy;
use Modules\Patient\Models\Patient;

/**
 * @extends Factory<Allergy>
 */
class AllergyFactory extends Factory
{
    protected $model = Allergy::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'allergen' => fake()->randomElement([
                'Penicillin',
                'Amoxicillin',
                'Sulfa drugs',
                'Aspirin',
                'Ibuprofen',
                'Latex',
                'Peanuts',
                'Tree nuts',
                'Shellfish',
                'Eggs',
                'Milk',
                'Soy',
                'Wheat',
                'Bee venom',
                'Polygons',
                'Dust mites',
                'Mold',
                'Pet dander',
            ]),
            'allergen_code' => fake()->optional()->numerify('T##.###'),
            'allergen_type' => fake()->randomElement(AllergenType::cases()),
            'reaction' => fake()->randomElement([
                'Rash, hives, or itching',
                'Swelling',
                'Difficulty breathing',
                'Anaphylaxis',
                'Nausea or vomiting',
                'Dizziness',
                'Stomach pain',
            ]),
            'severity' => fake()->randomElement(AllergySeverity::cases()),
            'onset_type' => fake()->randomElement(OnsetType::cases()),
            'is_active' => true,
            'onset_date' => fake()->dateTimeBetween('-5 years', 'now'),
            'verification_status' => 'verified',
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function medication(): static
    {
        return $this->state(fn (array $attributes) => [
            'allergen_type' => AllergenType::MEDICATION,
        ]);
    }

    public function food(): static
    {
        return $this->state(fn (array $attributes) => [
            'allergen_type' => AllergenType::FOOD,
        ]);
    }

    public function severe(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => AllergySeverity::SEVERE,
        ]);
    }

    public function lifeThreatening(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => AllergySeverity::LIFE_THREATENING,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => 'unverified',
        ]);
    }
}
