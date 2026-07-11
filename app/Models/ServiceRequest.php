<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Enums\RequestPriority;
use Modules\Clinical\Enums\RequestStatus;
use Modules\Core\Classes\Support\DocumentNumberGenerator;
use Modules\Core\Contracts\ProvidesClientIdentity;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Support\ClientIdentity;
use Modules\Core\Support\ClientIdentityResolver;
use Modules\Patient\Models\Patient;

/**
 * @property string|null $patient_id
 * @property string|null $guest_name
 * @property string|null $guest_phone
 * @property string|null $guest_email
 */
class ServiceRequest extends BaseModel implements ProvidesClientIdentity
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'request_number',
        'patient_id',
        'encounter_id',
        'branch_id',
        'status',
        'priority',
        'notes',
        'guest_name',
        'guest_phone',
        'guest_email',
        'ordered_by',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'status' => RequestStatus::class,
        'priority' => RequestPriority::class,
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceRequest $request) {
            if (empty($request->request_number)) {
                $request->request_number = static::generateRequestNumber();
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return ServiceRequestFactory::new();
    }

    public static function generateRequestNumber(): string
    {
        return app(DocumentNumberGenerator::class)->next(
            documentKey: 'service_request',
            prefix: 'SRQ',
        );
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

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'ordered_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequestItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::ACTIVE);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::COMPLETED);
    }

    public function scopeByPatient(Builder $query, string $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByEncounter(Builder $query, string $encounterId): Builder
    {
        return $query->where('encounter_id', $encounterId);
    }

    public function isActive(): bool
    {
        return $this->status === RequestStatus::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === RequestStatus::COMPLETED;
    }

    public function isGuest(): bool
    {
        return is_null($this->patient_id);
    }

    public function getDisplayName(): string
    {
        if ($this->isGuest()) {
            return "Guest Request - {$this->clientIdentity()->name}";
        }

        return "{$this->patient?->full_name} - {$this->request_number}";
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

    public function getTotalAmountAttribute(): float
    {
        if (array_key_exists('items_total_amount', $this->attributes)) {
            return (float) $this->attributes['items_total_amount'];
        }

        if (isset($this->items_total_amount)) {
            return (float) $this->items_total_amount;
        }

        return $this->items->sum('total_price');
    }

    public function getPendingItemsCountAttribute(): int
    {
        return $this->items->where('status', RequestItemStatus::PENDING)->count();
    }

    public function getCompletedItemsCountAttribute(): int
    {
        return $this->items->where('status', RequestItemStatus::COMPLETED)->count();
    }

    public function getProgressPercentageAttribute(): float
    {
        $total = (int) ($this->items_count ?? $this->items()->count());

        if ($total === 0) {
            return 0;
        }

        $completed = (int) ($this->completed_items_count ?? $this->items()
            ->where('status', RequestItemStatus::COMPLETED)
            ->count());

        return round(($completed / $total) * 100, 1);
    }

    public function isFullyFulfilled(): bool
    {
        return $this->items->every(fn ($item) => $item->status->isTerminal());
    }

    public function getAllFulfilledRoles(): Collection
    {
        return $this->items
            ->where('status', RequestItemStatus::COMPLETED)
            ->map(fn ($item) => $item->service?->roles)
            ->flatten()
            ->unique('id');
    }

    public function addItem(array $data): RequestItem
    {
        $service = Service::findOrFail($data['service_id']);

        return $this->items()->create([
            'service_id' => $data['service_id'],
            'service_variant_id' => $data['service_variant_id'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $service->getDefaultPrice(),
            'status' => RequestItemStatus::PENDING,
        ]);
    }

    public function calculateTotals(): void
    {
        foreach ($this->items as $item) {
            $item->update([
                'total_price' => ($item->unit_price * $item->quantity) - $item->discount_amount,
            ]);
        }
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => RequestStatus::COMPLETED]);
    }

    public function cancel(): void
    {
        $this->update(['status' => RequestStatus::CANCELLED]);
        $this->items()->where('status', RequestItemStatus::PENDING)
            ->update(['status' => RequestItemStatus::CANCELLED]);
    }
}
