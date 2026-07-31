<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NursingProblemStatus: string implements HasColor, HasDescription, HasLabel
{
    case ACTIVE = 'active';
    case RESOLVED = 'resolved';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::RESOLVED => 'Resolved',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::ACTIVE => 'Nursing problem currently requires care',
            self::RESOLVED => 'Nursing problem has been resolved',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ACTIVE => 'primary',
            self::RESOLVED => 'success',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
