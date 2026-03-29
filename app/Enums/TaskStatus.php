<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TaskStatus: string implements HasColor, HasDescription, HasLabel
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PENDING => 'Task waiting to be started',
            self::IN_PROGRESS => 'Task is being performed',
            self::COMPLETED => 'Task has been completed successfully',
            self::CANCELLED => 'Task was cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_PROGRESS => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'gray',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::PENDING;
    }

    public function isActive(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED]);
    }
}
