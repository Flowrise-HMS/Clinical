<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum OnsetType: string implements HasColor, HasDescription, HasLabel
{
    case ACUTE = 'acute';
    case CHRONIC = 'chronic';
    case UNKNOWN = 'unknown';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ACUTE => 'Acute',
            self::CHRONIC => 'Chronic',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACUTE => 'danger',
            self::CHRONIC => 'warning',
            self::UNKNOWN => 'gray',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::ACUTE => 'Sudden onset of allergic reaction',
            self::CHRONIC => 'Ongoing or recurring allergic condition',
            self::UNKNOWN => 'Onset pattern is unknown',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
