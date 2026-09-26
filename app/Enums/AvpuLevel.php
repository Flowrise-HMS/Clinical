<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Level of consciousness on the AVPU scale, plus "new confusion" as scored by SATS TEWS.
 */
enum AvpuLevel: string implements HasLabel
{
    case ALERT = 'alert';
    case CONFUSED = 'confused';
    case VOICE = 'voice';
    case PAIN = 'pain';
    case UNRESPONSIVE = 'unresponsive';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ALERT => 'Alert',
            self::CONFUSED => 'Confused',
            self::VOICE => 'Reacts to voice',
            self::PAIN => 'Reacts to pain',
            self::UNRESPONSIVE => 'Unresponsive',
        };
    }

    public function isAlert(): bool
    {
        return $this === self::ALERT;
    }
}
