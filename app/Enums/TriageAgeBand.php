<?php

namespace Modules\Clinical\Enums;

use Carbon\CarbonInterface;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Age-appropriate SATS TEWS version.
 */
enum TriageAgeBand: string implements HasDescription, HasLabel
{
    case ADULT = 'adult';
    case OLDER_CHILD = 'older_child';
    case YOUNGER_CHILD = 'younger_child';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::ADULT => 'Adult',
            self::OLDER_CHILD => 'Older child',
            self::YOUNGER_CHILD => 'Younger child',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::ADULT => 'Older than 12 years or taller than 150 cm',
            self::OLDER_CHILD => '3 to 12 years or 95 to 150 cm tall',
            self::YOUNGER_CHILD => 'Younger than 3 years or smaller than 95 cm',
        };
    }

    public function isPaediatric(): bool
    {
        return $this !== self::ADULT;
    }

    /**
     * Band from date of birth; adults when the date of birth is unknown.
     */
    public static function fromDateOfBirth(?CarbonInterface $dateOfBirth, ?CarbonInterface $on = null): self
    {
        if ($dateOfBirth === null) {
            return self::ADULT;
        }

        // Completed years of age (Carbon 3 returns a fractional difference).
        $years = (int) floor(abs($dateOfBirth->diffInYears($on ?? now())));

        return match (true) {
            $years < 3 => self::YOUNGER_CHILD,
            $years <= 12 => self::OLDER_CHILD,
            default => self::ADULT,
        };
    }
}
