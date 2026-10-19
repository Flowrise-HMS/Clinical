<?php

namespace Modules\Clinical\Filament\Support;

use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Modules\Clinical\Classes\Services\MedicationAdministrationService;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Contracts\PrescriptionScheduleCalculatorContract;
use Modules\Clinical\Enums\MedicationAdministrationStatus;
use Modules\Clinical\Models\RequestItem;

class MarScheduleFormSchema
{
    /**
     * @return array<int, mixed>
     */
    public static function forSingleItem(RequestItem $item): array
    {
        $detail = $item->loadMissing('prescriptionDetail')->prescriptionDetail;
        $policy = app(MedicationFulfillmentPolicy::class);
        $marService = app(MedicationAdministrationService::class);
        $calculator = app(PrescriptionScheduleCalculatorContract::class);

        $givenCount = $policy->givenDosesCount($item);
        $totalDoses = $detail?->total_administrations ?? '∞';
        $remaining = $marService->getRemainingDoses($item);

        $givenSlotSequences = $item->medicationAdministrations()
            ->where('status', MedicationAdministrationStatus::GIVEN)
            ->whereNotNull('dose_slot_sequence')
            ->pluck('dose_slot_sequence')
            ->map(fn ($seq): int => (int) $seq)
            ->all();

        return [
            Hidden::make('request_item_id')->default($item->id),

            Placeholder::make('medication_info')
                ->label(__('Prescription Details'))
                ->content(function () use ($item, $detail, $givenCount, $totalDoses, $remaining): HtmlString {
                    $medName = e($item->service?->name ?? 'Medication');
                    $sig = e((string) ($detail?->frequency ?? ''));
                    $dosage = e((string) ($detail?->dosage ?? ''));
                    $route = e((string) ($detail?->route ?? ''));
                    $duration = (int) ($detail?->duration_days ?? 1);

                    return new HtmlString("
                        <div class='rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700'>
                            <div class='font-semibold text-gray-900 dark:text-white text-sm'>{$medName}</div>
                            <div class='mt-1 text-gray-600 dark:text-gray-300 flex flex-wrap gap-x-4 gap-y-1'>
                                <span><strong>Dosage:</strong> {$dosage}</span>
                                <span><strong>Frequency:</strong> {$sig}</span>
                                <span><strong>Route:</strong> {$route}</span>
                                <span><strong>Duration:</strong> {$duration} day(s)</span>
                                <span><strong>Progress:</strong> {$givenCount} / {$totalDoses} given ({$remaining} remaining)</span>
                            </div>
                        </div>
                    ");
                }),

            DateTimePicker::make('course_started_at')
                ->label(__('First Dose / Start Time'))
                ->native(true)
                ->default($detail?->course_started_at ?? now())
                ->required()
                ->live()
                ->helperText(__('Select when the first dose is scheduled. Subsequent doses are automatically projected based on the prescription frequency.')),

            Select::make('schedule_type')
                ->label(__('Schedule Strategy'))
                ->options([
                    'interval_based' => __('Automate Interval from Start Time (e.g. BD = every 12h, TDS = every 8h)'),
                    'standard_times' => __('Standard Facility / Ward Times (e.g. 08:00, 20:00)'),
                    'custom_times' => __('Custom Daily Times'),
                ])
                ->default($detail?->schedule_type ?? 'interval_based')
                ->required()
                ->live()
                ->helperText(__('Choose how subsequent dosage slots should be planned across the treatment duration.')),

            TagsInput::make('custom_dose_times')
                ->label(__('Custom Daily Administration Times (HH:MM)'))
                ->placeholder(__('e.g. 10:00, 22:00'))
                ->default($detail?->custom_dose_times ?? ['08:00', '20:00'])
                ->visible(fn (Get $get): bool => $get('schedule_type') === 'custom_times')
                ->live()
                ->helperText(__('Enter 24-hour time entries (e.g. 09:00, 21:00) when doses should be administered each day.')),

            Placeholder::make('schedule_preview')
                ->label(__('Planned Dose Schedule Preview'))
                ->content(function (Get $get) use ($calculator, $detail, $givenSlotSequences): HtmlString {
                    $courseStartedAt = $get('course_started_at') ?: ($detail?->course_started_at ?? now());
                    $scheduleType = $get('schedule_type') ?: ($detail?->schedule_type ?? 'interval_based');
                    $customTimes = $get('custom_dose_times') ?: ($detail?->custom_dose_times ?? []);

                    $slots = $calculator->buildDoseSchedule((object) [
                        'frequency' => $detail?->frequency,
                        'duration_days' => $detail?->duration_days ?? 1,
                        'course_started_at' => $courseStartedAt,
                        'schedule_type' => $scheduleType,
                        'custom_dose_times' => $customTimes,
                        'total_administrations' => $detail?->total_administrations,
                        'prn' => (bool) ($detail?->prn ?? false),
                    ]);

                    if (empty($slots)) {
                        return new HtmlString('<p class="text-xs text-gray-500 italic">No scheduled dose slots for this prescription.</p>');
                    }

                    $rows = '';
                    foreach ($slots as $slot) {
                        $isGiven = in_array($slot->sequence, $givenSlotSequences, true);
                        $badgeClass = $isGiven
                            ? 'bg-success-100 text-success-800 dark:bg-success-900/40 dark:text-success-300'
                            : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300';
                        $statusLabel = $isGiven ? __('Given') : __('Scheduled');
                        $formattedDate = $slot->dueAt->format('M j, Y — H:i');

                        $rows .= "
                            <tr class='border-b border-gray-100 dark:border-gray-800 text-xs'>
                                <td class='py-1.5 px-2 font-medium text-gray-900 dark:text-gray-100'>Dose {$slot->sequence}</td>
                                <td class='py-1.5 px-2 text-gray-700 dark:text-gray-300'>{$formattedDate}</td>
                                <td class='py-1.5 px-2'>
                                    <span class='inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium {$badgeClass}'>
                                        {$statusLabel}
                                    </span>
                                </td>
                            </tr>
                        ";
                    }

                    return new HtmlString("
                        <div class='overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 max-h-56 overflow-y-auto'>
                            <table class='w-full text-left border-collapse'>
                                <thead class='bg-gray-50 dark:bg-gray-800 text-[11px] text-gray-500 uppercase tracking-wider font-semibold sticky top-0'>
                                    <tr>
                                        <th class='py-1 px-2'>#</th>
                                        <th class='py-1 px-2'>Planned Time</th>
                                        <th class='py-1 px-2'>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {$rows}
                                </tbody>
                            </table>
                        </div>
                    ");
                }),
        ];
    }
}
