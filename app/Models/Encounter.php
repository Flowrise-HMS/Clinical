<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Clinical\Classes\Services\AdtService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\DischargeDisposition;
use Modules\Clinical\Enums\EncounterPriority;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Core\Classes\Support\DocumentNumberGenerator;
use Modules\Core\Contracts\ProvidesClientIdentity;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Location;
use Modules\Core\Support\ClientIdentity;
use Modules\Core\Support\ClientIdentityResolver;
use Modules\Patient\Models\Patient;

/**
 * @property string|null $patient_id
 * @property string|null $guest_name
 * @property string|null $guest_phone
 * @property string|null $guest_email
 */
class Encounter extends BaseModel implements ProvidesClientIdentity
{
    /** @use HasFactory<EncounterFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'encounter_number',
        'patient_id',
        'branch_id',
        'location_id',
        'department_id',
        'type',
        'status',
        'priority',
        'chief_complaint',
        'coverage_type',
        'admitted_by',
        'discharged_by',
        'discharge_disposition',
        'transfer_destination',
        'admitted_at',
        'discharged_at',
        'bed_id',
        'guest_name',
        'guest_phone',
        'guest_email',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'type' => EncounterType::class,
        'status' => EncounterStatus::class,
        'priority' => EncounterPriority::class,
        'discharge_disposition' => DischargeDisposition::class,
        'coverage_type' => CoverageType::class,
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Encounter $encounter) {
            if (empty($encounter->encounter_number)) {
                $encounter->encounter_number = static::generateEncounterNumber();
            }
            if ($encounter->type === EncounterType::INPATIENT && empty($encounter->admitted_at)) {
                $encounter->admitted_at = now();
            }
        });

        static::updating(function (Encounter $encounter) {
            if ($encounter->isDirty('status') && $encounter->status === EncounterStatus::FINISHED) {
                $encounter->discharged_at = now();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return EncounterFactory::new();
    }

    public static function generateEncounterNumber(): string
    {
        return app(DocumentNumberGenerator::class)->next(
            documentKey: 'encounter',
            prefix: 'ENC',
        );
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'bed_id');
    }

    public function locationEvents(): HasMany
    {
        return $this->hasMany(EncounterLocationEvent::class)->orderByDesc('occurred_at');
    }

    public function admittedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'admitted_by');
    }

    public function dischargedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'discharged_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(EncounterParticipant::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class, 'encounter_id');
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class, 'encounter_id');
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(EncounterParticipant::class)->where('status', ParticipantStatus::ACTIVE);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EncounterStatus::PLANNED->value,
            EncounterStatus::ARRIVED->value,
            EncounterStatus::TRIAGED->value,
            EncounterStatus::IN_PROGRESS->value,
            EncounterStatus::ON_LEAVE->value,
        ]);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EncounterStatus::FINISHED->value,
            EncounterStatus::CANCELLED->value,
        ]);
    }

    public function scopeByPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByType(Builder $query, EncounterType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeInpatient(Builder $query): Builder
    {
        return $query->where('type', EncounterType::INPATIENT);
    }

    public function scopeOutpatient(Builder $query): Builder
    {
        return $query->where('type', EncounterType::OUTPATIENT);
    }

    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('type', EncounterType::EMERGENCY);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isInpatient(): bool
    {
        return $this->type === EncounterType::INPATIENT;
    }

    public function isGuest(): bool
    {
        return is_null($this->patient_id);
    }

    public function getDisplayName(): string
    {
        if ($this->isGuest()) {
            return $this->clientIdentity()->name;
        }

        return $this->patient?->full_name ?? 'N/A';
    }

    public function clientIdentity(): ClientIdentity
    {
        return ClientIdentityResolver::resolve(
            patientFullName: $this->patient?->full_name,
            patientMrn: $this->patient?->mrn,
            guestName: $this->guest_name,
            guestPhone: $this->guest_phone,
            guestEmail: $this->guest_email,
        );
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->admitted_at) {
            return null;
        }

        $end = $this->discharged_at ?? now();

        return $this->admitted_at->diffForHumans($end, true);
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (! $this->admitted_at) {
            return null;
        }

        $end = $this->discharged_at ?? now();

        return $this->admitted_at->diffInMinutes($end);
    }

    public function canTransitionTo(EncounterStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function addParticipant(int $userId, string $role): EncounterParticipant
    {
        return $this->participants()->create([
            'user_id' => $userId,
            'role' => $role,
            'status' => ParticipantStatus::ACTIVE,
            'joined_at' => now(),
        ]);
    }

    public function removeParticipant(int $userId): void
    {
        $this->participants()
            ->where('user_id', $userId)
            ->where('status', ParticipantStatus::ACTIVE)
            ->update([
                'status' => ParticipantStatus::COMPLETED,
                'left_at' => now(),
            ]);
    }

    public function discharge(?int $dischargedBy = null, ?DischargeDisposition $disposition = null): void
    {
        app(AdtService::class)->discharge(
            $this,
            $disposition,
            null,
            actedBy: $dischargedBy,
        );
    }

    public function transferParticipant(int $userId, int $newUserId, string $newRole): void
    {
        $this->removeParticipant($userId);
        $this->addParticipant($newUserId, $newRole);
    }
}
