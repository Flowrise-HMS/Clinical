<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NavigationGroup: string implements HasColor, HasLabel
{
    case CLINICAL = 'clinical';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::CLINICAL => 'Clinical',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CLINICAL => 'primary',
        };
    }
}
