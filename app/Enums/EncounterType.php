<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum EncounterType: string implements HasColor, HasDescription, HasLabel
{
    case INPATIENT = 'inpatient';
    case OUTPATIENT = 'outpatient';
    case EMERGENCY = 'emergency';
    case VIRTUAL = 'virtual';
    case HOME_VISIT = 'home_visit';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::INPATIENT => 'Inpatient',
            self::OUTPATIENT => 'Outpatient',
            self::EMERGENCY => 'Emergency',
            self::VIRTUAL => 'Virtual/Telemedicine',
            self::HOME_VISIT => 'Home Visit',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::INPATIENT => 'Patient admitted to a bed for care',
            self::OUTPATIENT => 'Patient visits without overnight stay',
            self::EMERGENCY => 'Urgent care for acute conditions',
            self::VIRTUAL => 'Remote consultation via telemedicine',
            self::HOME_VISIT => 'Healthcare services provided at patient home',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INPATIENT => 'primary',
            self::OUTPATIENT => 'info',
            self::EMERGENCY => 'danger',
            self::VIRTUAL => 'warning',
            self::HOME_VISIT => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isInpatient(): bool
    {
        return $this === self::INPATIENT;
    }

    public function requiresBed(): bool
    {
        return $this === self::INPATIENT;
    }
}
