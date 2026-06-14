<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum MedicationAdministrationStatus: string implements HasColor, HasDescription, HasLabel
{
    case GIVEN = 'given';
    case OMITTED = 'omitted';
    case REFUSED = 'refused';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GIVEN => 'Given',
            self::OMITTED => 'Omitted',
            self::REFUSED => 'Refused',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::GIVEN => 'Medication was administered to the patient',
            self::OMITTED => 'Dose was omitted',
            self::REFUSED => 'Patient refused the medication',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GIVEN => 'success',
            self::OMITTED => 'warning',
            self::REFUSED => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
