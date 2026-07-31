<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Database\Factories\CarePlanObjectiveFactory;
use Modules\Clinical\Enums\GoalAchievementStatus;
use Modules\Clinical\Enums\GoalLifecycleStatus;
use Modules\Core\Models\BaseModel;

class CarePlanObjective extends BaseModel
{
    /** @use HasFactory<CarePlanObjectiveFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_diagnosis_id',
        'description',
        'target_measure',
        'target_value',
        'target_date',
        'lifecycle_status',
        'achievement_status',
        'start_date',
        'author_id',
    ];

    protected $casts = [
        'target_date' => 'date',
        'lifecycle_status' => GoalLifecycleStatus::class,
        'achievement_status' => GoalAchievementStatus::class,
        'start_date' => 'date',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanObjectiveFactory::new();
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(CarePlanDiagnosis::class, 'care_plan_diagnosis_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'author_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(CarePlanEvaluation::class);
    }
}
