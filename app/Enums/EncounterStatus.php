<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum EncounterStatus: string implements HasColor, HasDescription, HasLabel
{
    case PLANNED = 'planned';
    case ARRIVED = 'arrived';
    case TRIAGED = 'triaged';
    case IN_PROGRESS = 'in_progress';
    case ON_LEAVE = 'on_leave';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::ARRIVED => 'Arrived',
            self::TRIAGED => 'Triaged',
            self::IN_PROGRESS => 'In Progress',
            self::ON_LEAVE => 'On Leave',
            self::FINISHED => 'Finished',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PLANNED => 'Encounter is scheduled but patient has not arrived',
            self::ARRIVED => 'Patient has arrived at the facility',
            self::TRIAGED => 'Patient has been triaged and categorized',
            self::IN_PROGRESS => 'Clinical care is being provided',
            self::ON_LEAVE => 'Patient temporarily left but will return',
            self::FINISHED => 'Encounter completed and patient discharged',
            self::CANCELLED => 'Encounter was cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PLANNED => 'gray',
            self::ARRIVED => 'info',
            self::TRIAGED => 'warning',
            self::IN_PROGRESS => 'primary',
            self::ON_LEAVE => 'secondary',
            self::FINISHED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::PLANNED;
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ARRIVED, self::TRIAGED, self::IN_PROGRESS, self::ON_LEAVE]);
    }

    public function isCompleted(): bool
    {
        return in_array($this, [self::FINISHED, self::CANCELLED]);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PLANNED => in_array($newStatus, [self::ARRIVED, self::CANCELLED]),
            self::ARRIVED => in_array($newStatus, [self::TRIAGED, self::CANCELLED]),
            self::TRIAGED => in_array($newStatus, [self::IN_PROGRESS, self::CANCELLED]),
            self::IN_PROGRESS => in_array($newStatus, [self::FINISHED, self::ON_LEAVE, self::CANCELLED]),
            self::ON_LEAVE => in_array($newStatus, [self::IN_PROGRESS, self::FINISHED, self::CANCELLED]),
            self::FINISHED, self::CANCELLED => false,
        };
    }
}
