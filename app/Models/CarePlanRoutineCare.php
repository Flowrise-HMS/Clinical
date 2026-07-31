<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\CarePlanRoutineCareFactory;
use Modules\Clinical\Enums\RoutineCareItem;
use Modules\Core\Models\BaseModel;

class CarePlanRoutineCare extends BaseModel
{
    /** @use HasFactory<CarePlanRoutineCareFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_id',
        'item',
        'specification',
        'not_applicable',
        'notes',
        'specified_by',
        'specified_at',
    ];

    protected $casts = [
        'item' => RoutineCareItem::class,
        'not_applicable' => 'boolean',
        'specified_at' => 'datetime',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanRoutineCareFactory::new();
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function specifiedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'specified_by');
    }
}
