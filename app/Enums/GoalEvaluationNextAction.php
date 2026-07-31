<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum GoalEvaluationNextAction: string implements HasColor, HasDescription, HasLabel
{
    case CONTINUE = 'continue';
    case REVISE = 'revise';
    case DISCONTINUE = 'discontinue';
    case ESCALATE = 'escalate';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::CONTINUE => 'Continue',
            self::REVISE => 'Revise',
            self::DISCONTINUE => 'Discontinue',
            self::ESCALATE => 'Escalate',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::CONTINUE => 'Continue the current care plan',
            self::REVISE => 'Revise the care plan',
            self::DISCONTINUE => 'Discontinue the care plan',
            self::ESCALATE => 'Escalate the patient care concern',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::CONTINUE => 'primary',
            self::REVISE => 'warning',
            self::DISCONTINUE => 'gray',
            self::ESCALATE => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
