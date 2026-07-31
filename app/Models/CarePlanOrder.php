<?php

namespace Modules\Clinical\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Clinical\Database\Factories\CarePlanOrderFactory;
use Modules\Clinical\Enums\CarePlanOrderStatus;
use Modules\Core\Models\BaseModel;

class CarePlanOrder extends BaseModel
{
    /** @use HasFactory<CarePlanOrderFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_diagnosis_id',
        'sequence',
        'instruction',
        'frequency',
        'status',
        'plannable_type',
        'plannable_id',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'status' => CarePlanOrderStatus::class,
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanOrderFactory::new();
    }

    public function diagnosis(): BelongsTo
    {
        return $this->belongsTo(CarePlanDiagnosis::class, 'care_plan_diagnosis_id');
    }

    public function plannable(): MorphTo
    {
        return $this->morphTo();
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(CarePlanIntervention::class);
    }
}
