<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum TriageMobility: string implements HasLabel
{
    /** Walking (adult) / normal for age (child). */
    case WALKING = 'walking';

    /** Walks with help (adult TEWS only). */
    case WITH_HELP = 'with_help';

    /** Stretcher / immobile (adult) or unable to walk / move as normal (child). */
    case IMMOBILE = 'immobile';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::WALKING => 'Walking / normal for age',
            self::WITH_HELP => 'Walks with help',
            self::IMMOBILE => 'Stretcher, immobile or unable to move as normal',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function optionsFor(TriageAgeBand $band): array
    {
        $cases = $band->isPaediatric()
            ? [self::WALKING, self::IMMOBILE]
            : [self::WALKING, self::WITH_HELP, self::IMMOBILE];

        return collect($cases)
            ->mapWithKeys(fn (self $case): array => [$case->value => match (true) {
                $band->isPaediatric() && $case === self::WALKING => 'Normal for age',
                $band->isPaediatric() && $case === self::IMMOBILE => 'Unable to walk / move as normal',
                $case === self::WALKING => 'Walking',
                $case === self::IMMOBILE => 'Stretcher / immobile',
                default => (string) $case->getLabel(),
            }])
            ->all();
    }
}
