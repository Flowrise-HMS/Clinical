<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum AllergyVerificationStatus: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case UNVERIFIED = 'unverified';
    case VERIFIED = 'verified';
    case REFUTED = 'refuted';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::UNVERIFIED => 'Unverified',
            self::VERIFIED => 'Verified',
            self::REFUTED => 'Refuted',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::UNVERIFIED => 'gray',
            self::VERIFIED => 'success',
            self::REFUTED => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::UNVERIFIED => 'heroicon-o-question-mark-circle',
            self::VERIFIED => 'heroicon-o-check-circle',
            self::REFUTED => 'heroicon-o-x-circle',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::UNVERIFIED => 'Allergy not yet verified by clinical staff',
            self::VERIFIED => 'Allergy confirmed and verified',
            self::REFUTED => 'Allergy has been ruled out',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
