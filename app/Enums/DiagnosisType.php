<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DiagnosisType: string implements HasColor, HasLabel
{
    case Primary = 'primary';
    case Secondary = 'secondary';
    case Complication = 'complication';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Secondary => 'Secondary',
            self::Complication => 'Complication',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Primary => 'primary',
            self::Secondary => 'gray',
            self::Complication => 'warning',
        };
    }
}
