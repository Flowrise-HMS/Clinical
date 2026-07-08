@if ($currentEncounter?->isActive())
    <div class="space-y-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Encounter</h3>
        <x-filament::section>
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-medium">{{ $currentEncounter->encounter_number }}</p>
                    <p class="text-sm text-gray-500">
                        {{ $currentEncounter->type?->getLabel() }} &middot;
                        {{ $currentEncounter->status?->getLabel() }}
                        @if ($currentEncounter->coverage_type ?? null)
                            &middot; {{ $currentEncounter->coverage_type->getLabel() }}
                        @endif
                    </p>
                </div>
                <x-filament::badge color="success">Active</x-filament::badge>
            </div>
        </x-filament::section>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            This patient already has an active encounter. Record vitals or proceed with assessment.
        </p>
        <div class="flex justify-end">
            <x-filament::button wire:click="$set('activeTab', 'vitals')" color="primary" icon="heroicon-m-heart">
                Go to Vitals
            </x-filament::button>
        </div>
    </div>
@else
    <div class="space-y-4 mt-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Create OPD Encounter</h3>
        {{ $this->encounterForm }}
        <div class="flex justify-end pt-4">
            <x-filament::button wire:click="createEncounter" color="primary" icon="heroicon-m-plus-circle">
                Create Encounter
            </x-filament::button>
        </div>
    </div>
@endif
