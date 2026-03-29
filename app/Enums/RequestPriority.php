<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Core\Enums\ServicePriority as CoreServicePriority;

enum RequestPriority: string implements HasColor, HasDescription, HasLabel
{
    case EMERGENCY = 'emergency';
    case URGENT = 'urgent';
    case ROUTINE = 'routine';
    case LOW = 'low';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::EMERGENCY => 'Emergency',
            self::URGENT => 'Urgent',
            self::ROUTINE => 'Routine',
            self::LOW => 'Low Priority',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::EMERGENCY => 'Immediately required, life-threatening',
            self::URGENT => 'Required soon, serious condition',
            self::ROUTINE => 'Standard turnaround acceptable',
            self::LOW => 'Can be done when convenient',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::EMERGENCY => 'danger',
            self::URGENT => 'warning',
            self::ROUTINE => 'primary',
            self::LOW => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::ROUTINE;
    }

    public function toCorePriority(): CoreServicePriority
    {
        return match ($this) {
            self::EMERGENCY => CoreServicePriority::EMERGENCY,
            self::URGENT => CoreServicePriority::URGENT,
            self::ROUTINE => CoreServicePriority::ROUTINE,
            self::LOW => CoreServicePriority::LOW,
        };
    }

    public static function fromCorePriority(CoreServicePriority $priority): self
    {
        return match ($priority) {
            CoreServicePriority::EMERGENCY => self::EMERGENCY,
            CoreServicePriority::URGENT => self::URGENT,
            CoreServicePriority::ROUTINE => self::ROUTINE,
            CoreServicePriority::LOW => self::LOW,
        };
    }
}
