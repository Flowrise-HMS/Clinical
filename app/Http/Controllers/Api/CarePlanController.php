<?php

namespace Modules\Clinical\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clinical\Classes\Services\CarePlanService;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Models\CarePlan;

/**
 * @group Clinical
 *
 * Care plans (header and lifecycle). Problems, goals and orders are maintained in the panel.
 */
class CarePlanController extends ClinicalApiController
{
    public function __construct(protected CarePlanService $plans) {}

    public static function model(): string
    {
        return CarePlan::class;
    }

    public function fields(): array
    {
        return ['id', 'patient_id', 'encounter_id', 'category', 'status', 'intent', 'title', 'description', 'period_start', 'period_end',
            'author_id', 'activated_at', 'completed_at', 'revoked_at', 'closure_reason', 'created_at', 'updated_at'];
    }

    public function filters(): array
    {
        return ['patient_id' => 'patient_id', 'encounter_id' => 'encounter_id', 'status' => 'status', 'category' => 'category'];
    }

    public function storeRules(): array
    {
        return [
            'encounter_id' => ['required', 'uuid'],
            'category' => ['required', Rule::enum(CarePlanCategory::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ];
    }

    public function updateRules(?Model $record = null): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'period_start' => ['sometimes', 'nullable', 'date'],
            'period_end' => ['sometimes', 'nullable', 'date'],
        ];
    }

    protected function createRecord(array $data): Model
    {
        $encounter = $this->encounter($data['encounter_id']);

        return $this->plans->create(
            $encounter->patient,
            $encounter,
            enum_from(CarePlanCategory::class, $data['category']),
            auth()->user(),
            collect($data)->only(['title', 'description', 'period_start', 'period_end'])->all(),
        );
    }

    /**
     * Activate a draft care plan.
     */
    public function activate(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (CarePlan $plan) => $this->plans->activate($plan));
    }

    /**
     * Put an active care plan on hold.
     */
    public function hold(string $id): JsonResponse
    {
        return $this->act($id, 'update', fn (CarePlan $plan) => $this->plans->hold($plan));
    }

    /**
     * Complete the care plan.
     *
     * @bodyParam closure_reason string Example: Goals met.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['closure_reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'update', fn (CarePlan $plan) => $this->plans->complete($plan, $data['closure_reason'] ?? null));
    }

    /**
     * Revoke the care plan.
     *
     * @bodyParam closure_reason string Example: Entered in error.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['closure_reason' => ['nullable', 'string', 'max:500']]);

        return $this->act($id, 'update', fn (CarePlan $plan) => $this->plans->revoke($plan, $data['closure_reason'] ?? null));
    }
}
