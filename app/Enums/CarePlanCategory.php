<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CarePlanCategory: string implements HasColor, HasDescription, HasLabel
{
    case NURSING = 'nursing';
    case WOUND = 'wound';
    case NUTRITION = 'nutrition';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::NURSING => 'Nursing',
            self::WOUND => 'Wound',
            self::NUTRITION => 'Nutrition',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::NURSING => 'General inpatient nursing care',
            self::WOUND => 'Wound assessment and treatment',
            self::NUTRITION => 'Nutrition assessment and support',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NURSING => 'primary',
            self::WOUND => 'warning',
            self::NUTRITION => 'info',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
