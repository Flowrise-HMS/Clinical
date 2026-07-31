<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Database\Factories\CarePlanProblemFactory;
use Modules\Clinical\Enums\NursingProblemStatus;
use Modules\Core\Models\BaseModel;

class CarePlanProblem extends BaseModel
{
    /** @use HasFactory<CarePlanProblemFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_id',
        'label',
        'description',
        'status',
        'priority',
        'identified_by',
    ];

    protected $casts = [
        'status' => NursingProblemStatus::class,
        'priority' => 'integer',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanProblemFactory::new();
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function identifiedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'identified_by');
    }

    public function strengths(): HasMany
    {
        return $this->hasMany(CarePlanProblemStrength::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(CarePlanDiagnosis::class);
    }
}
