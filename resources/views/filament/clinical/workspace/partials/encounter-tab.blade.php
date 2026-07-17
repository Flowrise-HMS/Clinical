@php
    $openEncounter = $this->getOpenEncounter();
    $typeLabel = $openEncounter?->type?->getLabel() ?? 'Encounter';
@endphp
<div class="space-y-4 mt-4">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $openEncounter ? "Active {$typeLabel} Encounter" : 'Create Encounter' }}
        </h3>
        @if ($openEncounter)
            <x-filament::badge color="success">
                {{ $openEncounter->encounter_number }} &middot; {{ $openEncounter->status?->getLabel() }}
            </x-filament::badge>
        @endif
    </div>

    @if ($this->hasOpenEncounter())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            This patient already has an open encounter. Review the details below or continue to vitals / ADT.
        </p>
    @endif

    {{ $this->encounterForm }}

    <div class="flex justify-end gap-2 pt-4">
        @if ($this->hasOpenEncounter())
            @if ($openEncounter?->type?->isInpatient())
                <x-filament::button wire:click="$set('activeTab', 'adt')" color="info" icon="heroicon-m-arrows-right-left">
                    Go to ADT
                </x-filament::button>
            @endif
            <x-filament::button wire:click="$set('activeTab', 'vitals')" color="primary" icon="heroicon-m-heart">
                Go to Vitals
            </x-filament::button>
        @endif

        <x-filament::button
            wire:click="createEncounter"
            color="primary"
            icon="heroicon-m-plus-circle"
            :disabled="$this->hasOpenEncounter()"
        >
            Create Encounter
        </x-filament::button>
    </div>
</div>
