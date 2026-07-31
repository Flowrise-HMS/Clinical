<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum GoalAchievementStatus: string implements HasColor, HasDescription, HasLabel
{
    case IN_PROGRESS = 'in-progress';
    case IMPROVING = 'improving';
    case WORSENING = 'worsening';
    case NO_CHANGE = 'no-change';
    case ACHIEVED = 'achieved';
    case SUSTAINING = 'sustaining';
    case NOT_ACHIEVED = 'not-achieved';
    case NO_PROGRESS = 'no-progress';
    case NOT_ATTAINABLE = 'not-attainable';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            self::IMPROVING => 'Improving',
            self::WORSENING => 'Worsening',
            self::NO_CHANGE => 'No Change',
            self::ACHIEVED => 'Achieved',
            self::SUSTAINING => 'Sustaining',
            self::NOT_ACHIEVED => 'Not Achieved',
            self::NO_PROGRESS => 'No Progress',
            self::NOT_ATTAINABLE => 'Not Attainable',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::IN_PROGRESS => 'Goal is being worked toward',
            self::IMPROVING => 'Patient is improving toward the goal',
            self::WORSENING => 'Patient is worsening relative to the goal',
            self::NO_CHANGE => 'No change has been observed',
            self::ACHIEVED => 'Goal has been achieved',
            self::SUSTAINING => 'Goal achievement is being sustained',
            self::NOT_ACHIEVED => 'Goal has not been achieved',
            self::NO_PROGRESS => 'No progress has been observed',
            self::NOT_ATTAINABLE => 'Goal is not attainable',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IN_PROGRESS => 'primary',
            self::IMPROVING => 'info',
            self::WORSENING, self::NOT_ACHIEVED => 'danger',
            self::NO_CHANGE, self::NOT_ATTAINABLE => 'gray',
            self::ACHIEVED, self::SUSTAINING => 'success',
            self::NO_PROGRESS => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
