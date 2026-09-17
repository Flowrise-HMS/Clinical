<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Clinical\Database\Factories\DischargeSummaryFactory;
use Modules\Clinical\Enums\DischargeCondition;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\DischargeSummaryStatus;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

/**
 * Structured record of an inpatient stay's outcome, authored by the treating
 * clinician and signed before (or at) discharge.
 *
 * @property string $id
 * @property string $encounter_id
 * @property ?string $patient_id
 * @property ?string $branch_id
 * @property ?string $admission_diagnosis
 * @property ?array<int, array<string, mixed>> $discharge_diagnoses
 * @property ?string $presenting_complaint
 * @property ?string $hospital_course
 * @property ?string $procedures
 * @property ?DischargeCondition $condition_at_discharge
 * @property ?array<int, array<string, mixed>> $discharge_medications
 * @property ?string $instructions
 * @property ?string $diet
 * @property ?string $activity
 * @property ?Carbon $follow_up_at
 * @property ?string $follow_up_notes
 * @property ?string $follow_up_provider_id
 * @property ?DischargeDisposition $disposition
 * @property DischargeSummaryStatus $status
 * @property ?int $authored_by
 * @property ?int $signed_by
 * @property ?Carbon $signed_at
 * @property ?array<string, mixed> $metadata
 * @property-read Encounter $encounter
 * @property-read ?Patient $patient
 * @property-read ?User $author
 * @property-read ?User $signer
 */
class DischargeSummary extends Model
{
    /** @use HasFactory<DischargeSummaryFactory> */
    use HasFactory, HasUuids;

    protected $table = 'discharge_summaries';

    protected $keyType = 'string';

    protected $fillable = [
        'encounter_id',
        'patient_id',
        'branch_id',
        'admission_diagnosis',
        'discharge_diagnoses',
        'presenting_complaint',
        'hospital_course',
        'procedures',
        'condition_at_discharge',
        'discharge_medications',
        'instructions',
        'diet',
        'activity',
        'follow_up_at',
        'follow_up_notes',
        'follow_up_provider_id',
        'disposition',
        'status',
        'authored_by',
        'signed_by',
        'signed_at',
        'metadata',
    ];

    protected $casts = [
        'discharge_diagnoses' => 'array',
        'discharge_medications' => 'array',
        'condition_at_discharge' => DischargeCondition::class,
        'disposition' => DischargeDisposition::class,
        'status' => DischargeSummaryStatus::class,
        'follow_up_at' => 'datetime',
        'signed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return DischargeSummaryFactory::new();
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function isSigned(): bool
    {
        return $this->status->isSigned();
    }
}
