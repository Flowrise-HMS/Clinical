<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Database\Factories\CarePlanFactory;
use Modules\Clinical\Enums\CarePlanCategory;
use Modules\Clinical\Enums\CarePlanIntent;
use Modules\Clinical\Enums\CarePlanStatus;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class CarePlan extends BaseModel
{
    /** @use HasFactory<CarePlanFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'branch_id',
        'category',
        'status',
        'intent',
        'title',
        'description',
        'period_start',
        'period_end',
        'discharge_date',
        'operation',
        'operation_date',
        'no_known_allergies',
        'custodian_id',
        'author_id',
        'activated_at',
        'completed_at',
        'revoked_at',
        'closure_reason',
    ];

    protected $casts = [
        'category' => CarePlanCategory::class,
        'status' => CarePlanStatus::class,
        'intent' => CarePlanIntent::class,
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'discharge_date' => 'date',
        'operation_date' => 'date',
        'no_known_allergies' => 'boolean',
        'activated_at' => 'datetime',
        'completed_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return CarePlanFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'custodian_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'author_id');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(CarePlanProblem::class);
    }

    public function routineCares(): HasMany
    {
        return $this->hasMany(CarePlanRoutineCare::class);
    }

    public function medicalDiagnoses(): BelongsToMany
    {
        return $this->belongsToMany(EncounterDiagnosis::class, 'care_plan_medical_diagnoses')
            ->withTimestamps();
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(CarePlanDiagnosis::class);
    }
}
