<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskOutcome: string implements HasColor, HasDescription, HasLabel
{
    case COMPLETED = 'completed';
    case PARTIAL = 'partial';
    case NO_SHOW = 'no_show';
    case PENDING_PAYMENT = 'pending_payment';
    case CANCELLED = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
            self::PARTIAL => 'Partially Completed',
            self::NO_SHOW => 'Patient No-Show',
            self::PENDING_PAYMENT => 'Pending Payment',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::COMPLETED => 'Service was fully rendered',
            self::PARTIAL => 'Service was partially completed',
            self::NO_SHOW => 'Patient did not appear for appointment',
            self::PENDING_PAYMENT => 'Service pending due to payment issue',
            self::CANCELLED => 'Service was cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::PARTIAL => 'warning',
            self::NO_SHOW => 'danger',
            self::PENDING_PAYMENT => 'warning',
            self::CANCELLED => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::COMPLETED;
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
