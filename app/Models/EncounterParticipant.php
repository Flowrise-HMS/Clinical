<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Database\Factories\EncounterParticipantFactory;
use Modules\Clinical\Enums\ParticipantRole;
use Modules\Clinical\Enums\ParticipantStatus;
use Modules\Core\Models\CoreUser;

class EncounterParticipant extends Model
{
    /** @use HasFactory<EncounterParticipantFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'encounter_id',
        'user_id',
        'role',
        'status',
        'joined_at',
        'left_at',
        'notes',
    ];

    protected $casts = [
        'role' => ParticipantRole::class,
        'status' => ParticipantStatus::class,
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    protected static function newFactory(): Factory
    {
        return EncounterParticipantFactory::new();
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'user_id');
    }

    public function isActive(): bool
    {
        return $this->status === ParticipantStatus::ACTIVE;
    }

    public function isPhysician(): bool
    {
        return $this->role->isPhysician();
    }

    public function isNurse(): bool
    {
        return $this->role->isNurse();
    }

    public function complete(): void
    {
        $this->update([
            'status' => ParticipantStatus::COMPLETED,
            'left_at' => now(),
        ]);
    }

    public function getShiftDurationAttribute(): ?int
    {
        if (! $this->joined_at) {
            return null;
        }

        $end = $this->left_at ?? now();

        return $this->joined_at->diffInMinutes($end);
    }
}
