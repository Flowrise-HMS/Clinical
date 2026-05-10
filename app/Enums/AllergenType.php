<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum AllergenType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    case MEDICATION = 'medication';
    case FOOD = 'food';
    case ENVIRONMENTAL = 'environmental';
    case BIOLOGICAL = 'biological';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MEDICATION => 'Medication',
            self::FOOD => 'Food',
            self::ENVIRONMENTAL => 'Environmental',
            self::BIOLOGICAL => 'Biological',
            self::OTHER => 'Other',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MEDICATION => 'heroicon-o-beaker',
            self::FOOD => 'heroicon-o-cake',
            self::ENVIRONMENTAL => 'heroicon-o-sun',
            self::BIOLOGICAL => 'heroicon-o-virus',
            self::OTHER => 'heroicon-o-question-mark-circle',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::MEDICATION => 'Adverse reaction to medication or drug',
            self::FOOD => 'Adverse reaction to food or food additive',
            self::ENVIRONMENTAL => 'Reaction to environmental triggers',
            self::BIOLOGICAL => 'Reaction to biological substances',
            self::OTHER => 'Other or unspecified allergen type',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MEDICATION => 'danger',
            self::FOOD => 'warning',
            self::ENVIRONMENTAL => 'success',
            self::BIOLOGICAL => 'primary',
            self::OTHER => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
