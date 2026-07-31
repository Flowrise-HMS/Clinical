<?php

namespace Modules\Clinical\Classes\Services;

use Illuminate\Support\Facades\DB;
use Modules\Clinical\Enums\CarePlanOrderStatus;
use Modules\Clinical\Models\CarePlan;
use Modules\Clinical\Models\CarePlanDiagnosis;
use Modules\Clinical\Models\CarePlanOrder;

class CarePlanOrderService
{
    public function addOrder(
        CarePlanDiagnosis $diagnosis,
        string $instruction,
        string $frequency,
        ?int $sequence = null,
    ): CarePlanOrder {
        return DB::transaction(function () use ($diagnosis, $instruction, $frequency, $sequence): CarePlanOrder {
            $diagnosis = CarePlanDiagnosis::query()
                ->with('carePlan')
                ->lockForUpdate()
                ->findOrFail($diagnosis->id);

            $this->assertPlanIsOpen($diagnosis->carePlan);

            $sequence ??= $diagnosis->orders()
                ->lockForUpdate()
                ->max('sequence') + 1;

            return $diagnosis->orders()->create([
                'sequence' => $sequence,
                'instruction' => $instruction,
                'frequency' => $frequency,
                'status' => CarePlanOrderStatus::PLANNED,
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
