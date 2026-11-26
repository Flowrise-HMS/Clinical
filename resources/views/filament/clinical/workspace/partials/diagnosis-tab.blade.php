@php
    $writableEncounter = $this->writableEncounter();
    $savedCount = $this->savedDiagnosisCount();
@endphp
<div class="space-y-4">
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ __('Diagnoses for this visit') }}
            @if ($savedCount > 0)
                <span class="ms-1 text-sm font-normal text-gray-500 dark:text-gray-400">({{ trans_choice(':count recorded|:count recorded', $savedCount, ['count' => $savedCount]) }})</span>
            @endif
        </h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('These are the diagnoses recorded on the open encounter. Edit a row and save to update it in place; remove a row to retire it. ICD search uses WHO with a local catalogue fallback, or type a custom diagnosis name.') }}
        </p>
    </div>

    @if (! $writableEncounter)
        <x-filament::badge color="warning">{{ __('No active encounter') }}</x-filament::badge>
    @endif
    <br />


    {{ $this->diagnosisForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="saveDiagnoses" color="primary" icon="heroicon-m-document-check"
            :disabled="! $writableEncounter">
            {{ $savedCount > 0 ? __('Save changes') : __('Save diagnoses') }}
        </x-filament::button>
    </div>
</div>
