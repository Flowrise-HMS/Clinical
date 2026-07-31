<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\CarePlanEvaluationFactory;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalEvaluationNextAction;
use Modules\Clinical\Enums\GoalEvaluationOutcome;
use Modules\Core\Models\BaseModel;

class CarePlanEvaluation extends BaseModel
{
    /** @use HasFactory<CarePlanEvaluationFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_objective_id',
        'evaluated_by',
        'evaluated_at',
        'outcome',
        'findings',
        'next_action',
        'achievement_status_snapshot',
    ];

    protected $casts = [
        'evaluated_at' => 'datetime',
        'outcome' => GoalEvaluationOutcome::class,
        'next_action' => GoalEvaluationNextAction::class,
        'achievement_status_snapshot' => GoalAchievementStatus::class,
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanEvaluationFactory::new();
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(CarePlanObjective::class, 'care_plan_objective_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'evaluated_by');
    }
}
