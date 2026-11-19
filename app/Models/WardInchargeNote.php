<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Clinical\Enums\WardNotePriority;
use Modules\Clinical\Enums\WardNoteStatus;
use Modules\Clinical\Enums\WardNoteType;
use Modules\Clinical\Enums\WardShift;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Location;

/**
 * Ward-level operational note, shift handover entry, incident report, or directive.
 * Kept separate from patient-level ClinicalNote records.
 *
 * @property string $id
 * @property string $branch_id
 * @property string $location_id
 * @property WardNoteType $note_type
 * @property ?WardShift $shift
 * @property Carbon $note_date
 * @property WardNotePriority $priority
 * @property string $title
 * @property string $body
 * @property ?array<string, mixed> $structured_payload
 * @property ?string $related_encounter_id
 * @property ?string $related_bed_id
 * @property bool $is_pinned
 * @property bool $requires_acknowledgement
 * @property ?int $acknowledged_by
 * @property ?Carbon $acknowledged_at
 * @property ?string $escalated_to_role
 * @property ?Carbon $escalated_at
 * @property WardNoteStatus $status
 * @property int $authored_by
 * @property ?string $resolution_notes
 * @property ?int $resolved_by
 * @property ?Carbon $resolved_at
 */
class WardInchargeNote extends BaseModel
{
    use HasFactory, HasUuids;

    protected $table = 'ward_incharge_notes';

    protected $keyType = 'string';

    protected $fillable = [
        'branch_id',
        'location_id',
        'note_type',
        'shift',
        'note_date',
        'priority',
        'title',
        'body',
        'structured_payload',
        'related_encounter_id',
        'related_bed_id',
        'is_pinned',
        'requires_acknowledgement',
        'acknowledged_by',
        'acknowledged_at',
        'escalated_to_role',
        'escalated_at',
        'status',
        'authored_by',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'note_type' => WardNoteType::class,
            'shift' => WardShift::class,
            'note_date' => 'date',
            'priority' => WardNotePriority::class,
            'structured_payload' => 'array',
            'is_pinned' => 'boolean',
            'requires_acknowledgement' => 'boolean',
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'status' => WardNoteStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function relatedEncounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'related_encounter_id');
    }

    public function relatedBed(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'related_bed_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeForWard($query, string $wardId)
    {
        return $query->where('location_id', $wardId);
    }

    public function scopePinned($query)
    {
        return $query->where('is_pinned', true);
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('requires_acknowledgement', true)->whereNull('acknowledged_at');
    }
}
