<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Clinical Notes</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Document progress notes, consultations, and other clinical documentation for this patient.
        </p>
    </div>

    {{ $this->noteForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="saveClinicalNote" color="primary" icon="heroicon-m-check">
            Save Note
        </x-filament::button>
    </div>
</div>
