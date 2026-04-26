<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum AllergySeverity: string implements HasColor, HasDescription, HasLabel
{
    case MILD = 'mild';
    case MODERATE = 'moderate';
    case SEVERE = 'severe';
    case LIFE_THREATENING = 'life_threatening';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MILD => 'Mild',
            self::MODERATE => 'Moderate',
            self::SEVERE => 'Severe',
            self::LIFE_THREATENING => 'Life Threatening',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MILD => 'success',
            self::MODERATE => 'warning',
            self::SEVERE => 'danger',
            self::LIFE_THREATENING => 'danger',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::MILD => 'Minor reaction, no treatment required',
            self::MODERATE => 'Significant reaction, may require treatment',
            self::SEVERE => 'Serious reaction requiring medical intervention',
            self::LIFE_THREATENING => 'Anaphylaxis or life-threatening emergency',
        };
    }

    public function isHigh(): bool
    {
        return in_array($this, [self::SEVERE, self::LIFE_THREATENING]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
