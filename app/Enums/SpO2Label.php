<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum SpO2Label: string implements HasColor, HasDescription, HasLabel
{
    case NORMAL = 'normal';
    case LOW = 'low';
    case CRITICAL = 'critical';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NORMAL => 'Normal (95-100%)',
            self::LOW => 'Low (90-94%)',
            self::CRITICAL => 'Critical (<90%)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NORMAL => 'success',
            self::LOW => 'warning',
            self::CRITICAL => 'danger',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::NORMAL => 'SpO₂ within expected range on documented oxygen.',
            self::LOW => 'SpO₂ below target; may need review or oxygen titration.',
            self::CRITICAL => 'SpO₂ critically low; urgent assessment required.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
