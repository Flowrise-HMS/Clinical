<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Where the patient goes after triage.
 */
enum TriageDisposition: string implements HasDescription, HasLabel
{
    case RESUSCITATION = 'resuscitation';
    case CONSULTATION = 'consultation';
    case BOOK_APPOINTMENT = 'book_appointment';
    case REQUEST_ADMISSION = 'request_admission';
    case REFER_OUT = 'refer_out';
    case DISCHARGE_HOME = 'discharge_home';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::RESUSCITATION => 'Resuscitation',
            self::CONSULTATION => 'Wait for consultation',
            self::BOOK_APPOINTMENT => 'Book an appointment',
            self::REQUEST_ADMISSION => 'Request admission',
            self::REFER_OUT => 'Refer to another facility',
            self::DISCHARGE_HOME => 'Discharge home',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::RESUSCITATION => 'Move to resus now; the encounter is started immediately.',
            self::CONSULTATION => 'Joins the queue in triage-priority order.',
            self::BOOK_APPOINTMENT => 'Not urgent today; schedule a clinic visit.',
            self::REQUEST_ADMISSION => 'Ask a ward to admit the patient.',
            self::REFER_OUT => 'Transfer care to another facility.',
            self::DISCHARGE_HOME => 'No further care needed today.',
        };
    }

    public static function defaultFor(TriageCategory $category): self
    {
        return match ($category) {
            TriageCategory::RED => self::RESUSCITATION,
            default => self::CONSULTATION,
        };
    }
}
