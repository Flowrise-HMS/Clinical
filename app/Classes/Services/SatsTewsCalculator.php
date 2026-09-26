<?php

namespace Modules\Clinical\Classes\Services;

use Modules\Clinical\Classes\Support\Triage\TewsResult;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageMobility;

/**
 * South African Triage Scale - Triage Early Warning Score (TEWS), per the SATS
 * training manual 2012 adult, older child and younger child charts.
 *
 * Numeric bands are listed as [inclusive upper bound, score] in ascending order;
 * a value above the last bound takes the final "above" score.
 */
class SatsTewsCalculator
{
    /**
     * @var array<string, array<string, array{bands: array<int, array{0: float, 1: int}>, above: int}>>
     */
    private const NUMERIC_BANDS = [
        'adult' => [
            // less than 9: 2 | 9-14: 0 | 15-20: 1 | 21-29: 2 | more than 29: 3
            'respiratory_rate' => ['bands' => [[8, 2], [14, 0], [20, 1], [29, 2]], 'above' => 3],
            // less than 41: 2 | 41-50: 1 | 51-100: 0 | 101-110: 1 | 111-129: 2 | more than 129: 3
            'heart_rate' => ['bands' => [[40, 2], [50, 1], [100, 0], [110, 1], [129, 2]], 'above' => 3],
            // less than 71: 3 | 71-80: 2 | 81-100: 1 | 101-199: 0 | more than 199: 2
            'systolic_bp' => ['bands' => [[70, 3], [80, 2], [100, 1], [199, 0]], 'above' => 2],
        ],
        'older_child' => [
            // less than 15: 3 | 15-16: 2 | 17-21: 0 | 22-26: 1 | 27 or more: 2
            'respiratory_rate' => ['bands' => [[14, 3], [16, 2], [21, 0], [26, 1]], 'above' => 2],
            // less than 60: 3 | 60-79: 2 | 80-99: 0 | 100-129: 1 | 130 or more: 2
            'heart_rate' => ['bands' => [[59, 3], [79, 2], [99, 0], [129, 1]], 'above' => 2],
        ],
        'younger_child' => [
            // less than 20: 3 | 20-25: 2 | 26-39: 0 | 40-49: 2 | 50 or more: 3
            'respiratory_rate' => ['bands' => [[19, 3], [25, 2], [39, 0], [49, 2]], 'above' => 3],
            // less than 70: 3 | 70-79: 2 | 80-130: 0 | 131-159: 2 | 160 or more: 3
            'heart_rate' => ['bands' => [[69, 3], [79, 2], [130, 0], [159, 2]], 'above' => 3],
        ],
    ];

    /**
     * @param  array{respiratory_rate?: int|float|string|null, heart_rate?: int|float|string|null, systolic_bp?: int|float|string|null, temperature?: int|float|string|null}  $vitals
     */
    public function calculate(
        TriageAgeBand $band,
        array $vitals,
        ?TriageMobility $mobility,
        ?AvpuLevel $avpu,
        bool $trauma = false,
    ): TewsResult {
        $breakdown = [
            'mobility' => ['value' => $mobility?->value, 'score' => $mobility ? $this->mobilityScore($band, $mobility) : null],
            'respiratory_rate' => $this->numeric($band, 'respiratory_rate', $vitals['respiratory_rate'] ?? null),
            'heart_rate' => $this->numeric($band, 'heart_rate', $vitals['heart_rate'] ?? null),
        ];

        if (! $band->isPaediatric()) {
            $breakdown['systolic_bp'] = $this->numeric($band, 'systolic_bp', $vitals['systolic_bp'] ?? null);
        }

        $temperature = $this->number($vitals['temperature'] ?? null);
        $breakdown['temperature'] = [
            'value' => $temperature,
            'score' => $temperature === null ? null : ($temperature < 35 || $temperature > 38.4 ? 2 : 0),
        ];
        $breakdown['avpu'] = ['value' => $avpu?->value, 'score' => $avpu ? $this->avpuScore($avpu) : null];
        $breakdown['trauma'] = ['value' => $trauma, 'score' => $trauma ? 1 : 0];

        $score = array_sum(array_map(fn (array $part): int => $part['score'] ?? 0, $breakdown));
        $missing = array_keys(array_filter($breakdown, fn (array $part): bool => $part['score'] === null));

        return new TewsResult(
            band: $band,
            score: $score,
            category: TriageCategory::fromTews($score),
            breakdown: $breakdown,
            missing: $missing,
            prompts: $this->prompts($breakdown, $avpu),
        );
    }

    /**
     * @return array{value: float|null, score: int|null}
     */
    private function numeric(TriageAgeBand $band, string $component, mixed $raw): array
    {
        $value = $this->number($raw);

        if ($value === null) {
            return ['value' => null, 'score' => null];
        }

        $table = self::NUMERIC_BANDS[$band->value][$component];

        foreach ($table['bands'] as [$upperBound, $score]) {
            if ($value <= $upperBound) {
                return ['value' => $value, 'score' => $score];
            }
        }

        return ['value' => $value, 'score' => $table['above']];
    }

    private function mobilityScore(TriageAgeBand $band, TriageMobility $mobility): int
    {
        return match ($mobility) {
            TriageMobility::WALKING => 0,
            // "With help" only exists on the adult chart; a child who cannot walk as normal scores 2.
            TriageMobility::WITH_HELP => $band->isPaediatric() ? 2 : 1,
            TriageMobility::IMMOBILE => 2,
        };
    }

    private function avpuScore(AvpuLevel $avpu): int
    {
        return match ($avpu) {
            AvpuLevel::ALERT => 0,
            AvpuLevel::VOICE => 1,
            AvpuLevel::CONFUSED, AvpuLevel::PAIN => 2,
            AvpuLevel::UNRESPONSIVE => 3,
        };
    }

    /**
     * @param  array<string, array{value: mixed, score: int|null}>  $breakdown
     * @return array<int, string>
     */
    private function prompts(array $breakdown, ?AvpuLevel $avpu): array
    {
        $prompts = [];

        if (($breakdown['respiratory_rate']['score'] ?? 0) >= 1) {
            $prompts[] = 'Respiratory rate scores on TEWS: check SpO2 and hand over to a senior clinician for oxygen.';
        }

        if ($avpu !== null && ! $avpu->isAlert()) {
            $prompts[] = 'Reduced level of consciousness: do a finger-prick glucose test and hand over to a senior clinician.';
        }

        return $prompts;
    }

    private function number(mixed $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }
}
