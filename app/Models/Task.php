<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Modules\Clinical\Database\Factories\TaskFactory;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Enums\TaskStatus;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\CoreUser;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;

class Task extends BaseModel
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'request_item_id',
        'status',
        'outcome',
        'performed_by',
        'started_at',
        'completed_at',
        'duration_minutes',
        'notes',
        'results',
        'metadata',
    ];

    protected $casts = [
        'status' => TaskStatus::class,
        'outcome' => TaskOutcome::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'duration_minutes' => 'integer',
        'results' => 'array',
        'metadata' => 'array',
    ];

    protected static function newFactory(): Factory
    {
        return TaskFactory::new();
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'performed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::PENDING);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::IN_PROGRESS);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::COMPLETED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', TaskStatus::CANCELLED);
    }

    public function isPending(): bool
    {
        return $this->status === TaskStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === TaskStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === TaskStatus::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function getServiceAttribute(): ?Service
    {
        return $this->requestItem?->service;
    }

    public function getPatientAttribute(): ?Patient
    {
        return $this->requestItem?->serviceRequest?->patient;
    }

    public function start(?int $performedBy = null): void
    {
        $this->update([
            'status' => TaskStatus::IN_PROGRESS,
            'performed_by' => $performedBy ?? Auth::id(),
            'started_at' => now(),
        ]);
    }

    public function complete(TaskOutcome $outcome = TaskOutcome::COMPLETED, ?array $results = null, ?string $notes = null): void
    {
        $startedAt = $this->started_at ?? now();

        $this->update([
            'status' => TaskStatus::COMPLETED,
            'outcome' => $outcome,
            'completed_at' => now(),
            'duration_minutes' => $startedAt->diffInMinutes(now()),
            'results' => $results ?? $this->results,
            'notes' => $notes ?? $this->notes,
        ]);

        $this->requestItem?->markAsFulfilled($this->performed_by);
    }

    public function cancel(?string $reason = null): void
    {
        $this->update([
            'status' => TaskStatus::CANCELLED,
            'outcome' => TaskOutcome::CANCELLED,
            'notes' => $reason ?? $this->notes,
        ]);
    }

    public function addResult(string $key, mixed $value): void
    {
        $results = $this->results ?? [];
        $results[$key] = $value;

        $this->update(['results' => $results]);
    }

    public function getDurationDisplayAttribute(): string
    {
        if (! $this->duration_minutes) {
            return '-';
        }

        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes}m";
    }
}
