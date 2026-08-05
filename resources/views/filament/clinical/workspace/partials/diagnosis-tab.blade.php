<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Diagnosis</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Record primary, secondary, and complication diagnoses for the active encounter. ICD search uses WHO with a local catalogue fallback; you can also enter a custom diagnosis name.
        </p>
    </div>

    @if (!$this->currentEncounter)
        <x-filament::badge color="warning">No active encounter</x-filament::badge>
    @endif

    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/50">
        <p class="text-sm font-medium text-gray-900 dark:text-white">ICD auto-suggestion</p>
        <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
            Type a free-text diagnosis description to get a suggested ICD code.
        </p>
        <div class="flex items-center gap-2">
            <input
                type="text"
                wire:model.debounce.500ms="autocodeText"
                placeholder="e.g. severe malaria with convulsions"
                class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400"
            >
            @if ($this->autocodeSuggestion)
                <x-filament::badge color="success" icon="heroicon-m-sparkles">
                    {{ $this->autocodeSuggestion['code'] ?? '' }} — {{ $this->autocodeSuggestion['label'] ?? '' }}
                </x-filament::badge>
            @endif
        </div>
        @if ($this->autocodeSuggestion)
            <div class="mt-2 flex justify-end">
                <x-filament::button wire:click="applyAutocodeToDiagnosis" size="sm" color="primary" icon="heroicon-m-arrow-down-tray">
                    Use suggestion
                </x-filament::button>
            </div>
        @endif
    </div>


    {{ $this->diagnosisForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="saveDiagnoses" color="primary" icon="heroicon-m-document-check"
            :disabled="!$this->currentEncounter">
            Save Diagnoses
        </x-filament::button>
    </div>
</div>
