@if ($postRegistrationFlow && ! $currentEncounter?->isActive())
    <div class="rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-900/20 p-4">
        <div class="flex items-start gap-3">
            <x-filament::icon name="heroicon-m-information-circle"
                class="w-5 h-5 text-primary-600 dark:text-primary-400 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-medium text-primary-900 dark:text-primary-100">
                    Patient registered — create an encounter to continue
                </p>
                <p class="text-sm text-primary-700 dark:text-primary-300 mt-1">
                    Set coverage type (Cash or NHIS), then record vitals before consultation.
                </p>
            </div>
        </div>
    </div>
@endif
