<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum GoalEvaluationOutcome: string implements HasColor, HasDescription, HasLabel
{
    case MET = 'met';
    case PARTIALLY_MET = 'partially_met';
    case NOT_MET = 'not_met';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::MET => 'Met',
            self::PARTIALLY_MET => 'Partially Met',
            self::NOT_MET => 'Not Met',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::MET => 'Goal was met at evaluation',
            self::PARTIALLY_MET => 'Goal was partially met at evaluation',
            self::NOT_MET => 'Goal was not met at evaluation',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MET => 'success',
            self::PARTIALLY_MET => 'warning',
            self::NOT_MET => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
