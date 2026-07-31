<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CarePlanIntent: string implements HasColor, HasDescription, HasLabel
{
    case PLAN = 'plan';

    public function getLabel(): string|Htmlable|null
    {
        return 'Plan';
    }

    public function getDescription(): ?string
    {
        return 'A plan for coordinated patient care';
    }

    public function getColor(): string|array|null
    {
        return 'primary';
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
