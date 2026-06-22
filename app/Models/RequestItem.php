<?php

namespace Modules\Clinical\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceVariant;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Models\PrescriptionDetail;

class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'service_request_id',
        'service_id',
        'service_variant_id',
        'quantity',
        'unit_price',
        'discount_amount',
        'total_price',
        'status',
        'fulfilled_by',
        'fulfilled_at',
        'notes',
        'billing_unit_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'status' => RequestItemStatus::class,
        'fulfilled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (RequestItem $item) {
            if ($item->isDirty('quantity') || $item->isDirty('unit_price') || $item->isDirty('discount_amount')) {
                $item->total_price = ($item->unit_price * $item->quantity) - $item->discount_amount;
            }
        });
    }

    protected static function newFactory(): Factory
    {
        return RequestItemFactory::new();
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function serviceVariant(): BelongsTo
    {
        return $this->belongsTo(ServiceVariant::class);
    }

    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', User::class), 'fulfilled_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function prescriptionDetail(): HasOne
    {
        return $this->hasOne(PrescriptionDetail::class, 'request_item_id');
    }

    public function medicationAdministrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    public function billingUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'billing_unit_id');
    }

    public function invoiceLine(): MorphOne
    {
        return $this->morphOne(InvoiceLine::class, 'billable');
    }

    public function getPaymentStatusAttribute(): ?InvoiceLineStatus
    {
        return $this->invoiceLine?->line_status;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', RequestItemStatus::PENDING);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', RequestItemStatus::IN_PROGRESS);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', RequestItemStatus::COMPLETED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', RequestItemStatus::CANCELLED);
    }

    public function isPending(): bool
    {
        return $this->status === RequestItemStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === RequestItemStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === RequestItemStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RequestItemStatus::CANCELLED;
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function getSubtotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }

    public function getLineDiscountAttribute(): float
    {
        return $this->discount_amount ?? 0;
    }

    public function getServiceNameAttribute(): string
    {
        $name = $this->service?->name ?? 'Unknown Service';

        if ($this->serviceVariant) {
            $name .= " ({$this->serviceVariant->name})";
        }

        return $name;
    }

    public function getServiceCodeAttribute(): string
    {
        return $this->service?->code ?? 'N/A';
    }

    public function canBeFulfilledBy(User $user): bool
    {
        $service = $this->service;

        if (! $service) {
            return false;
        }

        $allowedRoles = $service->roles;

        if ($allowedRoles->isEmpty()) {
            return true;
        }

        return $user->hasAnyRole($allowedRoles->pluck('name')->toArray());
    }

    public function markAsFulfilled(int $fulfilledBy): void
    {
        $this->update([
            'status' => RequestItemStatus::COMPLETED,
            'fulfilled_by' => $fulfilledBy,
            'fulfilled_at' => now(),
        ]);

        $this->checkRequestCompletion();
    }

    public function markAsInProgress(): void
    {
        $this->update(['status' => RequestItemStatus::IN_PROGRESS]);
    }

    public function cancel(): void
    {
        $this->update(['status' => RequestItemStatus::CANCELLED]);
        $this->checkRequestCompletion();
    }

    protected function checkRequestCompletion(): void
    {
        $request = $this->serviceRequest;

        if ($request->isFullyFulfilled()) {
            $request->markAsCompleted();
        }
    }
}
