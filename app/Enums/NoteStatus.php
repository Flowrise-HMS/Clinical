<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NoteStatus: string implements HasColor, HasLabel
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
            self::DRAFT => 'gray',
            self::SIGNED => 'success',
            self::AMENDED => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canBeEdited(): bool
    {
        return $this === self::DRAFT;
    }
}
