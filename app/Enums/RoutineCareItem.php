<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum RoutineCareItem: string implements HasColor, HasDescription, HasLabel
{
    case TPR = 'tpr';
    case BP = 'bp';
    case DIET = 'diet';
    case FLUIDS = 'fluids';
    case INTAKE_OUTPUT = 'intake_output';
    case ORAL_HYGIENE = 'oral_hygiene';
    case BATH = 'bath';
    case URINE_TESTING = 'urine_testing';
    case BODY_WEIGHT = 'body_weight';
    case ACTIVITY = 'activity';
    case OTHER = 'other';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::TPR => 'TPR',
            self::BP => 'Blood Pressure',
            self::DIET => 'Diet',
            self::FLUIDS => 'Fluids',
            self::INTAKE_OUTPUT => 'Intake and Output',
            self::ORAL_HYGIENE => 'Oral Hygiene',
            self::BATH => 'Bath',
            self::URINE_TESTING => 'Urine Testing',
            self::BODY_WEIGHT => 'Body Weight',
            self::ACTIVITY => 'Activity',
            self::OTHER => 'Other',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::TPR => 'Temperature, pulse, and respiration monitoring',
            self::BP => 'Blood pressure monitoring',
            self::DIET => 'Dietary care instructions',
            self::FLUIDS => 'Fluid care instructions',
            self::INTAKE_OUTPUT => 'Fluid intake and output monitoring',
            self::ORAL_HYGIENE => 'Oral hygiene care',
            self::BATH => 'Bathing and personal hygiene care',
            self::URINE_TESTING => 'Urine testing instructions',
            self::BODY_WEIGHT => 'Body weight monitoring',
            self::ACTIVITY => 'Activity and mobility instructions',
            self::OTHER => 'Other routine care instruction',
        };
    }

    public function getColor(): string|array|null
    {
        return 'primary';
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
