<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DiagnosisCertainty: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';
    case Provisional = 'provisional';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Confirmed => 'Confirmed',
            self::Provisional => 'Provisional',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Provisional => 'warning',
        };
    }
}
