<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DischargeSummaryStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case SIGNED = 'signed';
    case AMENDED = 'amended';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SIGNED => 'Signed',
            self::AMENDED => 'Amended',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::SIGNED => 'success',
            self::AMENDED => 'info',
        };
    }

    public function isSigned(): bool
    {
        return $this !== self::DRAFT;
    }
}
