<?php

namespace Modules\Clinical\Classes\Support\Triage;

use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;

/**
 * Outcome of a SATS Triage Early Warning Score calculation.
 */
final readonly class TewsResult
{
    /**
     * @param  array<string, array{value: mixed, score: int|null}>  $breakdown  per component; score null when not recorded
     * @param  array<int, string>  $missing  components that were not recorded (scored as 0)
     * @param  array<int, string>  $prompts  SATS "additional investigation" reminders
     */
    public function __construct(
        public TriageAgeBand $band,
        public int $score,
        public TriageCategory $category,
        public array $breakdown,
        public array $missing,
        public array $prompts,
    ) {}

    public function isComplete(): bool
    {
        return $this->missing === [];
    }
}
