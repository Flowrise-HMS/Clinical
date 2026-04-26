<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\VitalSignFactory;
use Modules\Clinical\Enums\PatientPosition;
use Modules\Clinical\Enums\SpO2Label;
use Modules\Clinical\Enums\SpO2Parameter;
use Modules\Clinical\Enums\VitalSignType;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class VitalSign extends BaseModel
{
    /** @use HasFactory<VitalSignFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'branch_id',
        'recorded_by',
        'recorded_at',
        'type',
        'position',
        'measurement_location',
        'systolic_bp',
        'diastolic_bp',
        'heart_rate',
        'respiratory_rate',
        'temperature',
        'spo2',
        'spo2_label',
        'spo2_parameter',
        'weight',
        'height',
        'bmi',
        'pain_level',
        'gcs_eye',
        'gcs_verbal',
        'gcs_motor',
        'intake',
        'output',
        'fbs',
        'rbs',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'type' => VitalSignType::class,
        'position' => PatientPosition::class,
        'spo2_label' => SpO2Label::class,
        'spo2_parameter' => SpO2Parameter::class,
        'recorded_at' => 'datetime',
        'temperature' => 'decimal:1',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'bmi' => 'decimal:2',
        'intake' => 'decimal:2',
        'output' => 'decimal:2',
        'fbs' => 'decimal:2',
        'rbs' => 'decimal:2',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (VitalSign $vitalSign) {
            if (! $vitalSign->bmi) {
                if ($vitalSign->weight && $vitalSign->height) {
                    $heightInMeters = $vitalSign->height / 100;
                    $vitalSign->bmi = round($vitalSign->weight / ($heightInMeters * $heightInMeters), 2);
                }
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return VitalSignFactory::new();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'recorded_by');
    }

    public function scopeByPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByEncounter(Builder $query, string $encounterId): Builder
    {
        return $query->where('encounter_id', $encounterId);
    }

    public function scopeByType(Builder $query, VitalSignType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('recorded_at', '>=', now()->subDays($days));
    }

    public function getBloodPressureAttribute(): ?string
    {
        if (! $this->systolic_bp || ! $this->diastolic_bp) {
            return null;
        }

        return "{$this->systolic_bp}/{$this->diastolic_bp}";
    }

    public function getGcsTotalAttribute(): ?int
    {
        if (! $this->gcs_eye || ! $this->gcs_verbal || ! $this->gcs_motor) {
            return null;
        }

        return $this->gcs_eye + $this->gcs_verbal + $this->gcs_motor;
    }

    public function getBmiCategoryAttribute(): ?string
    {
        if (! $this->bmi) {
            return null;
        }

        return match (true) {
            $this->bmi < 18.5 => 'Underweight',
            $this->bmi < 25 => 'Normal',
            $this->bmi < 30 => 'Overweight',
            default => 'Obese',
        };
    }

    public function isAbnormalBloodPressure(): bool
    {
        if (! $this->systolic_bp || ! $this->diastolic_bp) {
            return false;
        }

        return $this->systolic_bp >= 140 || $this->diastolic_bp >= 90;
    }

    public function isLowOxygenSaturation(): bool
    {
        return $this->spo2 !== null && $this->spo2 < 95;
    }
}
