<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Enums\MedicationAdministrationStatus;

class MedicationAdministration extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'request_item_id',
        'administered_by',
        'started_at',
        'ended_at',
        'quantity_given',
        'dose_unit_id',
        'status',
        'witness_confirmed',
        'omission_reason',
        'prn_reason',
        'dose_slot_sequence',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'quantity_given' => 'integer',
        'dose_slot_sequence' => 'integer',
        'witness_confirmed' => 'boolean',
        'status' => MedicationAdministrationStatus::class,
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function doseUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class, 'dose_unit_id');
    }

    public function countsAsGiven(): bool
    {
        return $this->status === MedicationAdministrationStatus::GIVEN;
    }
}
