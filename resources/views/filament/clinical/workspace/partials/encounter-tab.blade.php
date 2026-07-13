<div class="space-y-4 mt-4">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $this->hasOpenEncounter() ? 'Active OPD Encounter' : 'Create OPD Encounter' }}
        </h3>
        @if ($openEncounter = $this->getOpenEncounter())
            <x-filament::badge color="success">
                {{ $openEncounter->encounter_number }} &middot; {{ $openEncounter->status?->getLabel() }}
            </x-filament::badge>
        @endif
    </div>

    @if ($this->hasOpenEncounter())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            This patient already has an open encounter. Review the details below or continue to vitals.
        </p>
    @endif

    {{ $this->encounterForm }}

    <div class="flex justify-end gap-2 pt-4">
        @if ($this->hasOpenEncounter())
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
