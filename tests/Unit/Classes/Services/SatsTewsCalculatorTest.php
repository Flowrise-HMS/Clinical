<?php

use Modules\Clinical\Classes\Services\SatsTewsCalculator;
use Modules\Clinical\Classes\Support\Triage\SatsDiscriminators;
use Modules\Clinical\Enums\AvpuLevel;
use Modules\Clinical\Enums\TriageAgeBand;
use Modules\Clinical\Enums\TriageCategory;
use Modules\Clinical\Enums\TriageMobility;

/**
 * Thresholds from the SATS training manual 2012 TEWS charts.
 */
function tewsComponent(TriageAgeBand $band, string $component, int|float $value): ?int
{
    return (new SatsTewsCalculator)->calculate($band, [$component => $value], null, null)
        ->breakdown[$component]['score'];
}

dataset('adult respiratory rate', [
    [8, 2], [9, 0], [14, 0], [15, 1], [20, 1], [21, 2], [29, 2], [30, 3],
]);

dataset('adult heart rate', [
    [40, 2], [41, 1], [50, 1], [51, 0], [100, 0], [101, 1], [110, 1], [111, 2], [129, 2], [130, 3],
]);

dataset('adult systolic bp', [
    [70, 3], [71, 2], [80, 2], [81, 1], [100, 1], [101, 0], [199, 0], [200, 2],
]);

dataset('older child respiratory rate', [
    [14, 3], [15, 2], [16, 2], [17, 0], [21, 0], [22, 1], [26, 1], [27, 2],
]);

dataset('older child heart rate', [
    [59, 3], [60, 2], [79, 2], [80, 0], [99, 0], [100, 1], [129, 1], [130, 2],
]);

dataset('younger child respiratory rate', [
    [19, 3], [20, 2], [25, 2], [26, 0], [39, 0], [40, 2], [49, 2], [50, 3],
]);

dataset('younger child heart rate', [
    [69, 3], [70, 2], [79, 2], [80, 0], [130, 0], [131, 2], [159, 2], [160, 3],
]);

it('scores adult respiratory rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::ADULT, 'respiratory_rate', $value))->toBe($score))
    ->with('adult respiratory rate');

it('scores adult heart rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::ADULT, 'heart_rate', $value))->toBe($score))
    ->with('adult heart rate');

it('scores adult systolic blood pressure', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::ADULT, 'systolic_bp', $value))->toBe($score))
    ->with('adult systolic bp');

it('scores older child respiratory rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::OLDER_CHILD, 'respiratory_rate', $value))->toBe($score))
    ->with('older child respiratory rate');

it('scores older child heart rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::OLDER_CHILD, 'heart_rate', $value))->toBe($score))
    ->with('older child heart rate');

it('scores younger child respiratory rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::YOUNGER_CHILD, 'respiratory_rate', $value))->toBe($score))
    ->with('younger child respiratory rate');

it('scores younger child heart rate', fn (int $value, int $score) => expect(tewsComponent(TriageAgeBand::YOUNGER_CHILD, 'heart_rate', $value))->toBe($score))
    ->with('younger child heart rate');

it('scores temperature the same way on every chart', function (TriageAgeBand $band): void {
    expect(tewsComponent($band, 'temperature', 34.9))->toBe(2)
        ->and(tewsComponent($band, 'temperature', 35.0))->toBe(0)
        ->and(tewsComponent($band, 'temperature', 38.4))->toBe(0)
        ->and(tewsComponent($band, 'temperature', 38.5))->toBe(2);
})->with(TriageAgeBand::cases());

it('does not score systolic blood pressure for children', function (): void {
    $result = (new SatsTewsCalculator)->calculate(TriageAgeBand::OLDER_CHILD, ['systolic_bp' => 60], null, null);

    expect($result->breakdown)->not->toHaveKey('systolic_bp');
});

it('scores mobility, AVPU and trauma', function (): void {
    $calculator = new SatsTewsCalculator;

    expect($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WITH_HELP, AvpuLevel::ALERT)->score)->toBe(1)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::IMMOBILE, AvpuLevel::ALERT)->score)->toBe(2)
        ->and($calculator->calculate(TriageAgeBand::OLDER_CHILD, [], TriageMobility::WITH_HELP, AvpuLevel::ALERT)->score)->toBe(2)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WALKING, AvpuLevel::VOICE)->score)->toBe(1)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WALKING, AvpuLevel::CONFUSED)->score)->toBe(2)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WALKING, AvpuLevel::PAIN)->score)->toBe(2)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WALKING, AvpuLevel::UNRESPONSIVE)->score)->toBe(3)
        ->and($calculator->calculate(TriageAgeBand::ADULT, [], TriageMobility::WALKING, AvpuLevel::ALERT, trauma: true)->score)->toBe(1);
});

it('adds the components and maps the total to a SATS colour', function (): void {
    // RR 25 (2) + HR 115 (2) + SBP 95 (1) + temp 39 (2) + with help (1) + voice (1) + trauma (1) = 10
    $result = (new SatsTewsCalculator)->calculate(
        TriageAgeBand::ADULT,
        ['respiratory_rate' => 25, 'heart_rate' => 115, 'systolic_bp' => 95, 'temperature' => 39],
        TriageMobility::WITH_HELP,
        AvpuLevel::VOICE,
        trauma: true,
    );

    expect($result->score)->toBe(10)
        ->and($result->category)->toBe(TriageCategory::RED)
        ->and($result->isComplete())->toBeTrue()
        ->and($result->prompts)->toHaveCount(2);
});

it('maps TEWS totals to colours at the SATS cut-offs', function (): void {
    expect(TriageCategory::fromTews(0))->toBe(TriageCategory::GREEN)
        ->and(TriageCategory::fromTews(2))->toBe(TriageCategory::GREEN)
        ->and(TriageCategory::fromTews(3))->toBe(TriageCategory::YELLOW)
        ->and(TriageCategory::fromTews(4))->toBe(TriageCategory::YELLOW)
        ->and(TriageCategory::fromTews(5))->toBe(TriageCategory::ORANGE)
        ->and(TriageCategory::fromTews(6))->toBe(TriageCategory::ORANGE)
        ->and(TriageCategory::fromTews(7))->toBe(TriageCategory::RED);
});

it('flags vital signs that were not recorded', function (): void {
    $result = (new SatsTewsCalculator)->calculate(TriageAgeBand::ADULT, ['heart_rate' => 80], TriageMobility::WALKING, AvpuLevel::ALERT);

    expect($result->missing)->toBe(['respiratory_rate', 'systolic_bp', 'temperature'])
        ->and($result->score)->toBe(0);
});

it('uses the most severe discriminator for the band', function (): void {
    expect(SatsDiscriminators::categoryFor(TriageAgeBand::ADULT, ['moderate_pain', 'chest_pain']))->toBe(TriageCategory::ORANGE)
        ->and(SatsDiscriminators::categoryFor(TriageAgeBand::ADULT, ['cardiac_arrest', 'moderate_pain']))->toBe(TriageCategory::RED)
        ->and(SatsDiscriminators::categoryFor(TriageAgeBand::ADULT, []))->toBeNull()
        ->and(SatsDiscriminators::categoryFor(TriageAgeBand::YOUNGER_CHILD, ['tiny_baby']))->toBe(TriageCategory::ORANGE)
        ->and(SatsDiscriminators::filter(TriageAgeBand::ADULT, ['tiny_baby', 'chest_pain', 'made_up']))->toBe(['chest_pain']);
});

it('picks the most severe category and maps it to an encounter priority', function (): void {
    expect(TriageCategory::mostSevere(TriageCategory::GREEN, null, TriageCategory::ORANGE))->toBe(TriageCategory::ORANGE)
        ->and(TriageCategory::mostSevere(null, null))->toBeNull()
        ->and(TriageCategory::RED->toEncounterPriority()->value)->toBe('emergency')
        ->and(TriageCategory::YELLOW->toEncounterPriority()->value)->toBe('urgent')
        ->and(TriageCategory::GREEN->toEncounterPriority()->value)->toBe('routine')
        ->and(TriageCategory::ORANGE->targetMinutes())->toBe(10);
});

it('derives the TEWS version from age', function (): void {
    expect(TriageAgeBand::fromDateOfBirth(now()->subYears(2)))->toBe(TriageAgeBand::YOUNGER_CHILD)
        ->and(TriageAgeBand::fromDateOfBirth(now()->subYears(3)))->toBe(TriageAgeBand::OLDER_CHILD)
        ->and(TriageAgeBand::fromDateOfBirth(now()->subYears(12)))->toBe(TriageAgeBand::OLDER_CHILD)
        ->and(TriageAgeBand::fromDateOfBirth(now()->subYears(13)))->toBe(TriageAgeBand::ADULT)
        ->and(TriageAgeBand::fromDateOfBirth(null))->toBe(TriageAgeBand::ADULT);
});
