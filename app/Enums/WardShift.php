<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardShift: string implements HasColor, HasLabel
{
    case MORNING = 'morning';
    case AFTERNOON = 'afternoon';
    case NIGHT = 'night';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MORNING => 'Morning (08:00–14:00)',
            self::AFTERNOON => 'Afternoon (14:00–20:00)',
            self::NIGHT => 'Night (20:00–08:00)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MORNING => 'warning',
            self::AFTERNOON => 'info',
            self::NIGHT => 'gray',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
