<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardNoteStatus: string implements HasColor, HasLabel
{
    case OPEN = 'open';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::RESOLVED => 'Resolved',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::ACKNOWLEDGED => 'info',
            self::RESOLVED => 'success',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
