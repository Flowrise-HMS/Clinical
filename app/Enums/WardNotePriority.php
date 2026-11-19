<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardNotePriority: string implements HasColor, HasLabel
{
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NORMAL => 'Normal',
            self::IMPORTANT => 'Important',
            self::URGENT => 'Urgent',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NORMAL => 'gray',
            self::IMPORTANT => 'warning',
            self::URGENT => 'danger',
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
