<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardStatementStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case REVIEWED = 'reviewed';
    case AMENDED = 'amended';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::REVIEWED => 'Reviewed',
            self::AMENDED => 'Amended',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::SUBMITTED => 'info',
            self::REVIEWED => 'success',
            self::AMENDED => 'warning',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::AMENDED], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
