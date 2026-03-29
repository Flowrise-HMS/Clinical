<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum VitalSignType: string implements HasColor, HasDescription, HasLabel
{
    case ROUTINE = 'routine';
    case EMERGENCY = 'emergency';
    case ADMISSION = 'admission';
    case DISCHARGE = 'discharge';
    case PREOPERATIVE = 'preoperative';
    case POSTOPERATIVE = 'postoperative';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ROUTINE => 'Routine',
            self::EMERGENCY => 'Emergency',
            self::ADMISSION => 'Admission',
            self::DISCHARGE => 'Discharge',
            self::PREOPERATIVE => 'Pre-operative',
            self::POSTOPERATIVE => 'Post-operative',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::ROUTINE => 'Regular vital signs check',
            self::EMERGENCY => 'Emergency/trauma vital signs',
            self::ADMISSION => 'Admission assessment',
            self::DISCHARGE => 'Discharge assessment',
            self::PREOPERATIVE => 'Before surgery assessment',
            self::POSTOPERATIVE => 'After surgery assessment',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ROUTINE => 'info',
            self::EMERGENCY => 'danger',
            self::ADMISSION => 'primary',
            self::DISCHARGE => 'success',
            self::PREOPERATIVE => 'warning',
            self::POSTOPERATIVE => 'secondary',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
