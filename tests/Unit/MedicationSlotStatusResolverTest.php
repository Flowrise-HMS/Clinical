<?php

use Illuminate\Support\Carbon;
use Modules\Clinical\Classes\Services\MedicationSlotStatusResolver;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Enums\MedicationSlotStatus;
use Modules\Clinical\Models\MedicationAdministration;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'clinical.mar_reminders.lead_minutes' => 15,
        'clinical.mar_reminders.grace_minutes' => 30,
    ]);

    $this->now = Carbon::parse('2026-09-17 08:00:00');
    $this->resolver = new MedicationSlotStatusResolver;
});

it('derives the slot status from the clock', function (int $offsetMinutes, bool $isStat, MedicationSlotStatus $expected): void {
    $dueAt = $this->now->copy()->addMinutes($offsetMinutes);

    expect($this->resolver->resolve($dueAt, null, $isStat, $this->now))->toBe($expected);
})->with([
    'far future' => [120, false, MedicationSlotStatus::UPCOMING],
    'just outside lead window' => [16, false, MedicationSlotStatus::UPCOMING],
    'exactly at lead window' => [15, false, MedicationSlotStatus::DUE_SOON],
    'inside lead window' => [5, false, MedicationSlotStatus::DUE_SOON],
    'due this minute' => [0, false, MedicationSlotStatus::DUE_NOW],
    'inside grace window' => [-29, false, MedicationSlotStatus::DUE_NOW],
    'exactly at grace window' => [-30, false, MedicationSlotStatus::OVERDUE],
    'long overdue' => [-180, false, MedicationSlotStatus::OVERDUE],
    'stat inside lead window' => [10, true, MedicationSlotStatus::DUE_SOON],
    'stat far in the future' => [60, true, MedicationSlotStatus::UPCOMING],
    'stat due now' => [0, true, MedicationSlotStatus::DUE_NOW],
    'stat never ages into overdue' => [-240, true, MedicationSlotStatus::DUE_NOW],
]);

it('mirrors the recorded administration status regardless of the clock', function (MedicationAdministrationStatus $recorded, MedicationSlotStatus $expected): void {
    $administration = new MedicationAdministration(['status' => $recorded]);
    $dueAt = $this->now->copy()->subHours(5);

    expect($this->resolver->resolve($dueAt, $administration, false, $this->now))->toBe($expected)
        ->and($expected->isRecorded())->toBeTrue();
})->with([
    'given' => [MedicationAdministrationStatus::GIVEN, MedicationSlotStatus::GIVEN],
    'omitted' => [MedicationAdministrationStatus::OMITTED, MedicationSlotStatus::OMITTED],
    'refused' => [MedicationAdministrationStatus::REFUSED, MedicationSlotStatus::REFUSED],
]);
