<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardStatementPeriod: string implements HasColor, HasLabel
{
    case DAILY = 'daily';
    case MORNING = 'morning';
    case AFTERNOON = 'afternoon';
    case NIGHT = 'night';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DAILY => 'Daily (24-Hour)',
            self::MORNING => 'Morning Shift',
            self::AFTERNOON => 'Afternoon Shift',
            self::NIGHT => 'Night Shift',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DAILY => 'primary',
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
