<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Models\CanvasLayout;
use Modules\Patient\Models\Patient;

/**
 * @extends Factory<CanvasLayout>
 */
class CanvasLayoutFactory extends Factory
{
    protected $model = CanvasLayout::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'patient_id' => Patient::factory(),
            'canvas_key' => CanvasLayout::KEY_TIMELINE,
            'context_id' => null,
            'layout' => [
                'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1],
                'nodes' => [],
                'notes' => [],
                'edges' => [],
            ],
        ];
    }

    public function timeline(): static
    {
        return $this->state(fn (): array => [
            'canvas_key' => CanvasLayout::KEY_TIMELINE,
            'context_id' => null,
        ]);
    }

    public function medications(string $encounterId): static
    {
        return $this->state(fn (): array => [
            'canvas_key' => CanvasLayout::KEY_MEDICATIONS,
            'context_id' => $encounterId,
        ]);
    }
}
