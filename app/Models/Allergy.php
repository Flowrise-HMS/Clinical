<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Database\Factories\AllergyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Clinical\Enums\AllergenType;
use Modules\Clinical\Enums\AllergySeverity;
use Modules\Clinical\Enums\AllergyVerificationStatus;
use Modules\Clinical\Enums\OnsetType;
use Modules\Patient\Models\Patient;

class Allergy extends Model
{
    /** @use HasFactory<AllergyFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $keyType = 'string';

    protected $fillable = [
        'patient_id',
        'allergen',
        'allergen_code',
        'allergen_type',
        'reaction',
        'severity',
        'onset_type',
        'is_active',
        'onset_date',
        'verified_at',
        'verified_by',
        'verification_status',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allergen_type' => AllergenType::class,
        'severity' => AllergySeverity::class,
        'onset_type' => OnsetType::class,
        'verification_status' => AllergyVerificationStatus::class,
        'is_active' => 'boolean',
        'onset_date' => 'date',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'is_active' => true,
        'verification_status' => 'unverified',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'verified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('verification_status', AllergyVerificationStatus::VERIFIED);
    }

    public function scopeByType(Builder $query, AllergenType $type): Builder
    {
        return $query->where('allergen_type', $type);
    }

    public function scopeBySeverity(Builder $query, AllergySeverity $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeMedication(Builder $query): Builder
    {
        return $query->where('allergen_type', AllergenType::MEDICATION);
    }

    public function scopeFood(Builder $query): Builder
    {
        return $query->where('allergen_type', AllergenType::FOOD);
    }

    public function scopeEnvironmental(Builder $query): Builder
    {
        return $query->where('allergen_type', AllergenType::ENVIRONMENTAL);
    }

    public function scopeSevere(Builder $query): Builder
    {
        return $query->whereIn('severity', [
            AllergySeverity::SEVERE,
            AllergySeverity::LIFE_THREATENING,
        ]);
    }

    public function scopeForPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === AllergyVerificationStatus::VERIFIED;
    }

    public function isSevere(): bool
    {
        return in_array($this->severity, [
            AllergySeverity::SEVERE,
            AllergySeverity::LIFE_THREATENING,
        ]);
    }

    public function isLifeThreatening(): bool
    {
        return $this->severity === AllergySeverity::LIFE_THREATENING;
    }

    public function verify(User $verifier): void
    {
        $this->update([
            'verification_status' => AllergyVerificationStatus::VERIFIED,
            'verified_at' => now(),
            'verified_by' => $verifier->id,
        ]);
    }

    public function refute(User $verifier, ?string $reason = null): void
    {
        $this->update([
            'verification_status' => AllergyVerificationStatus::REFUTED,
            'verified_at' => now(),
            'verified_by' => $verifier->id,
            'notes' => $reason ? ($this->notes ? "{$this->notes}\n\nRefuted: {$reason}" : "Refuted: {$reason}") : $this->notes,
        ]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function reactivate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            AllergySeverity::MILD => 'success',
            AllergySeverity::MODERATE => 'warning',
            AllergySeverity::SEVERE => 'danger',
            AllergySeverity::LIFE_THREATENING => 'danger',
            default => 'gray',
        };
    }

    public function getDisplayLabelAttribute(): string
    {
        $label = $this->allergen;

        if ($this->allergen_code) {
            $label .= " ({$this->allergen_code})";
        }

        if ($this->isSevere()) {
            $label .= ' ⚠️';
        }

        if (! $this->isActive()) {
            $label .= ' (Inactive)';
        }

        return $label;
    }

    protected static function booted(): void
    {
        static::creating(function (Allergy $allergy) {
            if (auth()->check()) {
                $allergy->created_by = auth()->id();
                $allergy->updated_by = auth()->id();
            }
        });

        static::updating(function (Allergy $allergy) {
            if (auth()->check()) {
                $allergy->updated_by = auth()->id();
            }
        });
    }
}
