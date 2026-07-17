<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\EncounterLocationEventFactory;
use Modules\Clinical\Enums\AdtDestinationType;
use Modules\Clinical\Enums\AdtEventType;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Department;
use Modules\Core\Models\Location;
use Modules\Patient\Models\Patient;

class EncounterLocationEvent extends BaseModel
{
    /** @use HasFactory<EncounterLocationEventFactory> */
    use HasFactory, HasUuids;

    protected $table = 'encounter_location_events';

    protected $keyType = 'string';

    protected $fillable = [
        'branch_id',
        'encounter_id',
        'patient_id',
        'event_type',
        'from_bed_id',
        'to_bed_id',
        'from_location_id',
        'to_location_id',
        'from_department_id',
        'to_department_id',
        'destination_type',
        'destination_branch_id',
        'destination_label',
        'notes',
        'acted_by',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AdtEventType::class,
            'destination_type' => AdtDestinationType::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return EncounterLocationEventFactory::new();
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_bed_id');
    }

    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_bed_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'acted_by');
    }
}
