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
        'label',
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

    public static function composePes(
        ?string $problem = null,
        ?string $relatedTo = null,
        ?string $asEvidencedBy = null,
        ?string $fallbackLabel = null,
    ): string {
        $base = filled($problem) ? $problem : $fallbackLabel;
        $segments = [];

        if (filled($base)) {
            $segments[] = $base;
        }

        if (filled($relatedTo)) {
            $segments[] = 'related to '.$relatedTo;
        }

        if (filled($asEvidencedBy)) {
            $segments[] = 'as evidenced by '.$asEvidencedBy;
        }

        $composed = trim(implode(' ', $segments));

        return $composed !== '' ? $composed : (string) ($fallbackLabel ?? '');
    }

    public function displayLabel(): string
    {
        if ($this->relationLoaded('catalogue') && filled($this->catalogue?->label)) {
            return (string) $this->catalogue->label;
        }

        return (string) (
            $this->label
            ?? $this->problem_statement
            ?? $this->composed_statement
            ?? 'Nursing diagnosis'
        );
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
