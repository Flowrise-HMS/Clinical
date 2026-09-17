<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Clinical\Database\Factories\AdmissionRequestFactory;
use Modules\Clinical\Enums\AdmissionRequestStatus;
use Modules\Core\Contracts\ProvidesClientIdentity;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;
use Modules\Core\Support\ClientIdentity;
use Modules\Patient\Models\Patient;

/**
 * A clinician's request to admit a patient to a ward. The bed is only occupied
 * once ward staff accept the request and confirm the bed.
 *
 * @property string $id
 * @property string $encounter_id
 * @property ?string $patient_id
 * @property ?string $branch_id
 * @property string $requested_ward_id
 * @property ?string $requested_bed_id
 * @property ?string $assigned_bed_id
 * @property AdmissionRequestStatus $status
 * @property ?string $notes
 * @property ?int $requested_by
 * @property Carbon $requested_at
 * @property ?int $decided_by
 * @property ?Carbon $decided_at
 * @property ?string $decision_notes
 * @property-read Encounter $encounter
 * @property-read ?Patient $patient
 * @property-read Location $requestedWard
 * @property-read ?Location $requestedBed
 * @property-read ?Location $assignedBed
 * @property-read ?User $requester
 * @property-read ?User $decider
 */
class AdmissionRequest extends BaseModel implements ProvidesClientIdentity
{
    /** @use HasFactory<AdmissionRequestFactory> */
    use HasFactory, HasUuids;

    protected $table = 'admission_requests';

    protected $keyType = 'string';

    protected $fillable = [
        'encounter_id',
        'patient_id',
        'branch_id',
        'requested_ward_id',
        'requested_bed_id',
        'assigned_bed_id',
        'status',
        'notes',
        'requested_by',
        'requested_at',
        'decided_by',
        'decided_at',
        'decision_notes',
    ];

    protected $casts = [
        'status' => AdmissionRequestStatus::class,
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return AdmissionRequestFactory::new();
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

    public function requestedWard(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'requested_ward_id');
    }

    public function requestedBed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'requested_bed_id');
    }

    public function assignedBed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'assigned_bed_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AdmissionRequestStatus::Pending->value);
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function clientIdentity(): ClientIdentity
    {
        return $this->loadMissing('encounter.patient')->encounter->clientIdentity();
    }
}
