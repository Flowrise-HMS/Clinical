<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum NoteType: string implements HasColor, HasDescription, HasLabel
{
    case GENERAL = 'general';
    case PROGRESS = 'progress';
    case ADMISSION = 'admission';
    case DISCHARGE = 'discharge';
    case CONSULTATION = 'consultation';
    case NURSING = 'nursing';
    case PROCEDURE = 'procedure';
    case SURGERY = 'surgery';
    case RADIOLOGY = 'radiology';
    case LAB = 'lab';
    case MEDICATION = 'medication';
    case REFERRAL = 'referral';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::GENERAL => 'General Note',
            self::PROGRESS => 'Progress Note',
            self::ADMISSION => 'Admission Note',
            self::DISCHARGE => 'Discharge Note',
            self::CONSULTATION => 'Consultation Note',
            self::NURSING => 'Nursing Note',
            self::PROCEDURE => 'Procedure Note',
            self::SURGERY => 'Surgery Note',
            self::RADIOLOGY => 'Radiology Report',
            self::LAB => 'Lab Report',
            self::MEDICATION => 'Medication Note',
            self::REFERRAL => 'Referral Note',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GENERAL => 'gray',
            self::PROGRESS => 'info',
            self::ADMISSION => 'primary',
            self::DISCHARGE => 'success',
            self::CONSULTATION => 'warning',
            self::NURSING => 'secondary',
            self::PROCEDURE => 'danger',
            self::SURGERY => 'danger',
            self::RADIOLOGY => 'info',
            self::LAB => 'primary',
            self::MEDICATION => 'warning',
            self::REFERRAL => 'gray',
        };
    }

    public function getDescription(): string|Htmlable|null
    {
        return match ($this) {
            self::GENERAL => 'Unstructured or general clinical documentation.',
            self::PROGRESS => 'Interval update during an admission or course of care.',
            self::ADMISSION => 'Documentation at the start of an encounter or admission.',
            self::DISCHARGE => 'Summary and instructions at end of care.',
            self::CONSULTATION => 'Specialist opinion or consult letter.',
            self::NURSING => 'Nursing assessment, care plan, or shift note.',
            self::PROCEDURE => 'Documentation of a procedure performed.',
            self::SURGERY => 'Operative or perioperative documentation.',
            self::RADIOLOGY => 'Imaging report or radiology communication.',
            self::LAB => 'Laboratory result narrative or interpretation.',
            self::MEDICATION => 'Medication-related documentation or reconciliation.',
            self::REFERRAL => 'Referral request or response documentation.',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresStructuredContent(): bool
    {
        return in_array($this, [
            self::SURGERY,
            self::PROCEDURE,
            self::NURSING,
            self::RADIOLOGY,
            self::LAB,
            self::MEDICATION,
        ]);
    }
}
