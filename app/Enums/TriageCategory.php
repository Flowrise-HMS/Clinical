<?php

namespace Modules\Clinical\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

/**
 * South African Triage Scale (SATS) priority colours.
 */
enum TriageCategory: string implements HasColor, HasDescription, HasLabel
{
    case RED = 'red';
    case ORANGE = 'orange';
    case YELLOW = 'yellow';
    case GREEN = 'green';
    case BLUE = 'blue';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::RED => 'Red - Emergency',
            self::ORANGE => 'Orange - Very urgent',
            self::YELLOW => 'Yellow - Urgent',
            self::GREEN => 'Green - Routine',
            self::BLUE => 'Blue - Deceased',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::RED => 'Red',
            self::ORANGE => 'Orange',
            self::YELLOW => 'Yellow',
            self::GREEN => 'Green',
            self::BLUE => 'Blue',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::RED => 'Resuscitation: see immediately',
            self::ORANGE => 'See within 10 minutes',
            self::YELLOW => 'See within 60 minutes',
            self::GREEN => 'See within 4 hours',
            self::BLUE => 'Dead on arrival',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RED => Color::Red,
            self::ORANGE => Color::Orange,
            self::YELLOW => Color::Yellow,
            self::GREEN => Color::Green,
            self::BLUE => Color::Blue,
        };
    }

    /**
     * Target time to be seen by a clinician, in minutes; null when not applicable.
     */
    public function targetMinutes(): ?int
    {
        return match ($this) {
            self::RED => 0,
            self::ORANGE => 10,
            self::YELLOW => 60,
            self::GREEN => 240,
            self::BLUE => null,
        };
    }

    /**
     * Higher is more acute. Used to pick the most severe of several findings and to sort queues.
     */
    public function severityRank(): int
    {
        return match ($this) {
            self::RED => 4,
            self::ORANGE => 3,
            self::YELLOW => 2,
            self::GREEN => 1,
            self::BLUE => 0,
        };
    }

    public function toEncounterPriority(): EncounterPriority
    {
        return match ($this) {
            self::RED => EncounterPriority::EMERGENCY,
            self::ORANGE, self::YELLOW => EncounterPriority::URGENT,
            self::GREEN => EncounterPriority::ROUTINE,
            self::BLUE => EncounterPriority::LOW,
        };
    }

    /**
     * SATS TEWS bands: 0-2 green, 3-4 yellow, 5-6 orange, 7 or more red.
     */
    public static function fromTews(int $score): self
    {
        return match (true) {
            $score >= 7 => self::RED,
            $score >= 5 => self::ORANGE,
            $score >= 3 => self::YELLOW,
            default => self::GREEN,
        };
    }

    public static function mostSevere(?self ...$categories): ?self
    {
        $present = array_filter($categories);

        if ($present === []) {
            return null;
        }

        usort($present, fn (self $a, self $b): int => $b->severityRank() <=> $a->severityRank());

        return $present[0];
    }

    /**
     * Triage categories a clinician can be assigned to, most acute first (excludes Blue).
     *
     * @return array<int, self>
     */
    public static function clinical(): array
    {
        return [self::RED, self::ORANGE, self::YELLOW, self::GREEN];
    }
}
