<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum SpO2Parameter: string implements HasColor, HasDescription, HasLabel
{
    case ROOM_AIR = 'room_air';
    case NASAL_CANNULA = 'nasal_cannula';
    case NONREBREATHER = 'nonrebreather';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ROOM_AIR => 'Room Air',
            self::NASAL_CANNULA => 'Nasal Cannula',
            self::NONREBREATHER => 'Non-Rebreather Mask',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::ROOM_AIR => 'Patient breathing room air without supplemental oxygen',
            self::NASAL_CANNULA => 'Patient on nasal cannula oxygen delivery',
            self::NONREBREATHER => 'Patient on non-rebreather mask with reservoir bag',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ROOM_AIR => 'info',
            self::NASAL_CANNULA => 'warning',
            self::NONREBREATHER => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
