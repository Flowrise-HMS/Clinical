<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RequestStatus: string implements HasColor, HasDescription, HasLabel
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Request is being prepared',
            self::ACTIVE => 'Request submitted and awaiting fulfillment',
            self::COMPLETED => 'All items in request have been fulfilled',
            self::CANCELLED => 'Request was cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::ACTIVE;
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function canBeModified(): bool
    {
        return $this === self::DRAFT;
    }
}
