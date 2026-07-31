<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum GoalLifecycleStatus: string implements HasColor, HasDescription, HasLabel
{
    case PROPOSED = 'proposed';
    case PLANNED = 'planned';
    case ACCEPTED = 'accepted';
    case ACTIVE = 'active';
    case ON_HOLD = 'on-hold';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case ENTERED_IN_ERROR = 'entered-in-error';
    case REJECTED = 'rejected';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PROPOSED => 'Proposed',
            self::PLANNED => 'Planned',
            self::ACCEPTED => 'Accepted',
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On Hold',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
            self::ENTERED_IN_ERROR => 'Entered in Error',
            self::REJECTED => 'Rejected',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PROPOSED => 'Goal has been proposed for review',
            self::PLANNED => 'Goal has been planned',
            self::ACCEPTED => 'Goal has been accepted by the care team',
            self::ACTIVE => 'Goal is actively being pursued',
            self::ON_HOLD => 'Goal is temporarily paused',
            self::COMPLETED => 'Goal lifecycle is complete',
            self::CANCELLED => 'Goal has been cancelled',
            self::ENTERED_IN_ERROR => 'Goal was entered in error',
            self::REJECTED => 'Goal was rejected',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PROPOSED, self::PLANNED => 'gray',
            self::ACCEPTED, self::ACTIVE => 'primary',
            self::ON_HOLD => 'warning',
            self::COMPLETED => 'success',
            self::CANCELLED, self::ENTERED_IN_ERROR, self::REJECTED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
