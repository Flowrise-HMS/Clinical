<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Clinical\Classes\Support\Triage\SatsDiscriminators;
use Modules\Clinical\Database\Factories\TriageAssessmentFactory;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageDisposition;
use Modules\Clinical\Enums\TriageMobility;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

/**
 * A South African Triage Scale (SATS) assessment of an encounter.
 *
 * @property string $id
 * @property string $encounter_id
 * @property string $patient_id
 * @property ?string $branch_id
 * @property ?string $vital_sign_id
 * @property TriageAgeBand $age_band
 * @property ?TriageMobility $mobility
 * @property ?AvpuLevel $avpu
 * @property bool $trauma
 * @property ?int $tews_score
 * @property ?array<string, array{value: mixed, score: int|null}> $tews_breakdown
 * @property ?array<int, string> $discriminators
 * @property ?TriageCategory $tews_category
 * @property ?TriageCategory $discriminator_category
 * @property ?TriageCategory $override_category
 * @property ?string $override_reason
 * @property TriageCategory $final_category
 * @property ?TriageDisposition $disposition
 * @property ?string $notes
 * @property ?int $triaged_by
 * @property Carbon $triaged_at
 * @property-read Encounter $encounter
 * @property-read Patient $patient
 * @property-read ?VitalSign $vitalSign
 * @property-read ?User $triager
 */
class TriageAssessment extends BaseModel
{
    /** @use HasFactory<TriageAssessmentFactory> */
    use HasFactory, HasUuids;

    protected $table = 'triage_assessments';

    protected $keyType = 'string';

    protected $fillable = [
        'encounter_id',
        'patient_id',
        'branch_id',
        'vital_sign_id',
        'age_band',
        'mobility',
        'avpu',
        'trauma',
        'tews_score',
        'tews_breakdown',
        'discriminators',
        'tews_category',
        'discriminator_category',
        'override_category',
        'override_reason',
        'final_category',
        'disposition',
        'notes',
        'triaged_by',
        'triaged_at',
    ];

    protected $casts = [
        'age_band' => TriageAgeBand::class,
        'mobility' => TriageMobility::class,
        'avpu' => AvpuLevel::class,
        'trauma' => 'boolean',
        'tews_score' => 'integer',
        'tews_breakdown' => 'array',
        'discriminators' => 'array',
        'tews_category' => TriageCategory::class,
        'discriminator_category' => TriageCategory::class,
        'override_category' => TriageCategory::class,
        'final_category' => TriageCategory::class,
        'disposition' => TriageDisposition::class,
        'triaged_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return TriageAssessmentFactory::new();
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vitalSign(): BelongsTo
    {
        return $this->belongsTo(VitalSign::class);
    }

    public function triager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    /**
     * When the patient should be seen by, from the SATS target time.
     */
    public function targetAt(): ?Carbon
    {
        $minutes = $this->final_category->targetMinutes();

        return $minutes === null ? null : $this->triaged_at->copy()->addMinutes($minutes);
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        $target = $this->targetAt();

        return $target !== null && ($now ?? now())->greaterThan($target);
    }

    /**
     * @return array<int, array{key: string, label: string, category: TriageCategory}>
     */
    public function discriminatorDetails(): array
    {
        return SatsDiscriminators::describe($this->age_band, $this->discriminators ?? []);
    }
}
