<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum DischargeDisposition: string implements HasColor, HasDescription, HasLabel
{
    case COMPLETED = 'completed';
    case TRANSFERRED = 'transferred';
    case AGAINST_ADVICE = 'against_advice';
    case DECEASED = 'deceased';
    case REFERRED = 'referred';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::COMPLETED => 'Completed',
            self::TRANSFERRED => 'Transferred',
            self::AGAINST_ADVICE => 'Left Against Medical Advice (LAMA)',
            self::DECEASED => 'Deceased',
            self::REFERRED => 'Referred to Another Facility',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::COMPLETED => 'Patient discharged after successful treatment',
            self::TRANSFERRED => 'Patient transferred to another facility or unit',
            self::AGAINST_ADVICE => 'Patient chose to leave before treatment completion',
            self::DECEASED => 'Patient passed away during encounter',
            self::REFERRED => 'Patient referred to another healthcare provider',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::COMPLETED => 'success',
            self::TRANSFERRED => 'info',
            self::AGAINST_ADVICE => 'warning',
            self::DECEASED => 'danger',
            self::REFERRED => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::COMPLETED;
    }

    public function isNormalDischarge(): bool
    {
        return $this === self::COMPLETED;
    }
}
