<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DischargeCondition: string implements HasLabel
{
    case STABLE = 'stable';
    case IMPROVED = 'improved';
    case UNCHANGED = 'unchanged';
    case DETERIORATED = 'deteriorated';
    case DECEASED = 'deceased';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::STABLE => 'Stable',
            self::IMPROVED => 'Improved',
            self::UNCHANGED => 'Unchanged',
            self::DETERIORATED => 'Deteriorated',
            self::DECEASED => 'Deceased',
        };
    }
}
