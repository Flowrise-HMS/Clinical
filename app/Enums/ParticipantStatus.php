<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum ParticipantStatus: string implements HasColor, HasDescription, HasLabel
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::COMPLETED => 'Completed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::COMPLETED => 'gray',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::ACTIVE => 'Participant is currently part of the encounter.',
            self::COMPLETED => 'Participant involvement for this encounter has ended.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
