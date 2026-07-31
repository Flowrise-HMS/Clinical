<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CarePlanStatus: string implements HasColor, HasDescription, HasLabel
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ON_HOLD = 'on-hold';
    case COMPLETED = 'completed';
    case REVOKED = 'revoked';
    case ENTERED_IN_ERROR = 'entered-in-error';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::REVOKED => 'Revoked',
            self::ENTERED_IN_ERROR => 'Entered in Error',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::DRAFT => 'Care plan is being prepared',
            self::ACTIVE => 'Care plan is currently in effect',
            self::ON_HOLD => 'Care plan is temporarily paused',
            self::COMPLETED => 'Care plan has been completed',
            self::REVOKED => 'Care plan has been withdrawn',
            self::ENTERED_IN_ERROR => 'Care plan was entered in error',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ACTIVE => 'primary',
            self::ON_HOLD => 'warning',
            self::COMPLETED => 'success',
            self::REVOKED, self::ENTERED_IN_ERROR => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::DRAFT, self::ACTIVE, self::ON_HOLD]);
    }
}
