<?php

namespace Modules\Clinical\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\NursingProblemStatus;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanProblem;
use Modules\Clinical\Models\CarePlanProblemStrength;

class CarePlanProblemService
{
    public function identify(
        CarePlan $plan,
        string $label,
        User $identifiedBy,
        ?string $description = null,
        ?int $priority = null,
    ): CarePlanProblem {
        return DB::transaction(function () use ($plan, $label, $identifiedBy, $description, $priority): CarePlanProblem {
            $plan = CarePlan::query()->lockForUpdate()->findOrFail($plan->id);

            $this->assertPlanIsOpen($plan);

            return $plan->problems()->create([
                'label' => $label,
                'description' => $description,
                'status' => NursingProblemStatus::ACTIVE,
                'priority' => $priority,
                'identified_by' => $identifiedBy->id,
            ]);
        });
    }

    public function addStrength(
        CarePlanProblem $problem,
        string $description,
        User $identifiedBy,
    ): CarePlanProblemStrength {
        return DB::transaction(function () use ($problem, $description, $identifiedBy): CarePlanProblemStrength {
            $problem = CarePlanProblem::query()
                ->with('carePlan')
                ->lockForUpdate()
                ->findOrFail($problem->id);

            $this->assertPlanIsOpen($problem->carePlan);

            return $problem->strengths()->create([
                'description' => $description,
                'identified_by' => $identifiedBy->id,
                'identified_at' => now(),
            ]);
        });
    }

    protected function assertPlanIsOpen(CarePlan $plan): void
    {
        if (! $plan->status->isOpen()) {
            throw new \InvalidArgumentException(__('Only open care plans can be updated.'));
        }
    }
}
