<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Display state of one scheduled dose slot on the medication canvas. The first
 * three mirror the recorded administration; the rest are derived from the
 * clock using the MAR reminder lead/grace windows.
 */
enum MedicationSlotStatus: string implements HasColor, HasLabel
{
    case GIVEN = 'given';
    case OMITTED = 'omitted';
    case REFUSED = 'refused';
    case DUE_SOON = 'due_soon';
    case DUE_NOW = 'due_now';
    case OVERDUE = 'overdue';
    case UPCOMING = 'upcoming';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GIVEN => 'Given',
            self::OMITTED => 'Omitted',
            self::REFUSED => 'Refused',
            self::DUE_SOON => 'Due soon',
            self::DUE_NOW => 'Due now',
            self::OVERDUE => 'Overdue',
            self::UPCOMING => 'Upcoming',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GIVEN => 'success',
            self::OMITTED, self::REFUSED => 'gray',
            self::DUE_SOON, self::DUE_NOW => 'warning',
            self::OVERDUE => 'danger',
            self::UPCOMING => 'info',
        };
    }

    public function isRecorded(): bool
    {
        return in_array($this, [self::GIVEN, self::OMITTED, self::REFUSED], true);
    }
}
