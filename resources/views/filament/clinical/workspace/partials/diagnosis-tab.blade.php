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
    <br />


    {{ $this->diagnosisForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="saveDiagnoses" color="primary" icon="heroicon-m-document-check"
            :disabled="!$this->currentEncounter">
            Save Diagnoses
        </x-filament::button>
    </div>
</div>
