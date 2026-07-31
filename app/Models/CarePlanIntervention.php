<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\CarePlanInterventionFactory;
use Modules\Core\Models\BaseModel;

class CarePlanIntervention extends BaseModel
{
    /** @use HasFactory<CarePlanInterventionFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_order_id',
        'description',
        'performed_at',
        'performed_by',
        'notes',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanInterventionFactory::new();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(CarePlanOrder::class, 'care_plan_order_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'performed_by');
    }
}
