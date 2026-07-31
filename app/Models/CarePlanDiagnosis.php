<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Database\Factories\CarePlanDiagnosisFactory;
use Modules\Core\Models\BaseModel;

class CarePlanDiagnosis extends BaseModel
{
    /** @use HasFactory<CarePlanDiagnosisFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'care_plan_id',
        'care_plan_problem_id',
        'catalogue_id',
        'problem_statement',
        'related_to',
        'as_evidenced_by',
        'composed_statement',
        'recorded_at',
        'formulated_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    protected static function bootBelongsToBranch(): void {}

    protected static function newFactory(): Factory
    {
        return CarePlanDiagnosisFactory::new();
    }

    public static function composePes(string $problem, string $relatedTo, string $asEvidencedBy): string
    {
        return trim("{$problem} related to {$relatedTo} as evidenced by {$asEvidencedBy}");
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(CarePlanProblem::class, 'care_plan_problem_id');
    }

    public function catalogue(): BelongsTo
    {
        return $this->belongsTo(NursingDiagnosisCatalogue::class, 'catalogue_id');
    }

    public function formulatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'formulated_by');
    }

    public function objectives(): HasMany
    {
        return $this->hasMany(CarePlanObjective::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CarePlanOrder::class);
    }
}
