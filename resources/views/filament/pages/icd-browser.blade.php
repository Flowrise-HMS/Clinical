<x-filament-panels::page>
    <div class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">ICD-11 Browser</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Browse the WHO ICD-11 mortality and morbidity statistics (MMS) hierarchy, or search for a code.
                Selecting an entry dispatches an <code>icd-diagnosis-selected</code> event so the choice can be
                captured by the embedding page.
            </p>
        </div>

        @livewire(\Modules\Clinical\Filament\Components\IcdBrowser::class)
    </div>
</x-filament-panels::page>
