<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum WardNoteType: string implements HasColor, HasLabel
{
    case SHIFT_HANDOVER = 'shift_handover';
    case DAILY_AFFAIRS = 'daily_affairs';
    case INCIDENT = 'incident';
    case ESCALATION = 'escalation';
    case STAFFING = 'staffing';
    case DIRECTIVE = 'directive';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::SHIFT_HANDOVER => 'Shift Handover',
            self::DAILY_AFFAIRS => 'Daily Ward Affairs',
            self::INCIDENT => 'Ward Incident',
            self::ESCALATION => 'Escalation to Management / Matron',
            self::STAFFING => 'Staffing & Roster Note',
            self::DIRECTIVE => 'Directive / Notice',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SHIFT_HANDOVER => 'primary',
            self::DAILY_AFFAIRS => 'info',
            self::INCIDENT => 'danger',
            self::ESCALATION => 'warning',
            self::STAFFING => 'gray',
            self::DIRECTIVE => 'success',
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
