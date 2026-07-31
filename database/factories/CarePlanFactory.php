<?php

namespace Modules\Clinical\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanIntent;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\CarePlanProblemStrength;
use Modules\Clinical\Models\CarePlanRoutineCare;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\NursingDiagnosisCatalogue;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class CarePlanFactory extends Factory
{
    protected $model = CarePlan::class;

    public function definition(): array
    {
        return [
            'branch_id' => fn () => Branch::factory()->create()->id,
            'patient_id' => fn (array $attributes) => Patient::factory()
                ->state(['branch_id' => $attributes['branch_id']])
                ->create()
                ->id,
            'encounter_id' => fn (array $attributes) => Encounter::factory()
                ->forPatient(Patient::query()->findOrFail($attributes['patient_id']))
                ->create()
                ->id,
            'category' => CarePlanCategory::NURSING,
            'status' => CarePlanStatus::DRAFT,
            'intent' => CarePlanIntent::PLAN,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'period_start' => now(),
            'period_end' => null,
            'discharge_date' => null,
            'operation' => null,
            'operation_date' => null,
            'no_known_allergies' => false,
            'custodian_id' => null,
            'author_id' => User::factory(),
            'activated_at' => null,
            'completed_at' => null,
            'revoked_at' => null,
            'closure_reason' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CarePlanStatus::ACTIVE,
            'activated_at' => now(),
        ]);
    }

    public function withStrengths(int $count = 1): static
    {
        return $this->afterCreating(function (CarePlan $carePlan) use ($count): void {
            $problem = CarePlanProblem::factory()
                ->for($carePlan)
                ->create(['identified_by' => $carePlan->author_id]);

            CarePlanProblemStrength::factory()
                ->count($count)
                ->for($problem, 'problem')
                ->create(['identified_by' => $carePlan->author_id]);
        });
    }

    public function withRoutineCareComplete(): static
    {
        return $this->afterCreating(function (CarePlan $carePlan): void {
            foreach (RoutineCareItem::cases() as $item) {
                if ($item === RoutineCareItem::OTHER) {
                    continue;
                }

                CarePlanRoutineCare::factory()
                    ->for($carePlan)
                    ->create([
                        'item' => $item,
                        'specified_by' => $carePlan->author_id,
                    ]);
            }
        });
    }

    public function withDiagnosisAndOrders(int $orderCount = 3): static
    {
        return $this->afterCreating(function (CarePlan $carePlan) use ($orderCount): void {
            $problem = CarePlanProblem::factory()
                ->for($carePlan)
                ->create(['identified_by' => $carePlan->author_id]);

            $diagnosis = CarePlanDiagnosis::factory()
                ->for($carePlan)
                ->for($problem, 'problem')
                ->for(NursingDiagnosisCatalogue::factory(), 'catalogue')
                ->create(['formulated_by' => $carePlan->author_id]);

            foreach (range(1, $orderCount) as $sequence) {
                CarePlanOrder::factory()
                    ->for($diagnosis, 'diagnosis')
                    ->create(['sequence' => $sequence]);
            }
        });
    }
}
