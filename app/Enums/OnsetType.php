<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum OnsetType: string implements HasDescription, HasLabel
{
    case ACUTE = 'acute';
    case CHRONIC = 'chronic';
    case UNKNOWN = 'unknown';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ACUTE => 'Acute',
            self::CHRONIC => 'Chronic',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function getDescription(): ?string
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
