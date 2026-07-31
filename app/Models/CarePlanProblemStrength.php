<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\CarePlanProblemStrengthFactory;
use Modules\Core\Models\BaseModel;

class CarePlanProblemStrength extends BaseModel
{
    /** @use HasFactory<CarePlanProblemStrengthFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_problem_id',
        'description',
        'identified_by',
        'identified_at',
    ];

    protected $casts = [
        'identified_at' => 'datetime',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanProblemStrengthFactory::new();
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(CarePlanProblem::class, 'care_plan_problem_id');
    }

    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'identified_by');
    }
}
