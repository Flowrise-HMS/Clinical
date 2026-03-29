<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum PatientPosition: string implements HasColor, HasLabel
{
    case SITTING = 'sitting';
    case STANDING = 'standing';
    case SUPINE = 'supine';
    case PRONE = 'prone';
    case LATERAL = 'lateral';
    case FOWLERS = 'fowlers';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::SITTING => 'Sitting',
            self::STANDING => 'Standing',
            self::SUPINE => 'Supine (Lying on back)',
            self::PRONE => 'Prone (Lying on stomach)',
            self::LATERAL => 'Lateral (Lying on side)',
            self::FOWLERS => "Fowler's Position",
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SITTING => 'info',
            self::STANDING => 'success',
            self::SUPINE => 'primary',
            self::PRONE => 'warning',
            self::LATERAL => 'secondary',
            self::FOWLERS => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
