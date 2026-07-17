@php
    $openEncounter = $this->getOpenEncounter();
    $chip = $this->getEncounterStatusChip();
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Admit / Transfer / Discharge</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage bed assignment and patient movement for this visit.
            </p>
        </div>
        @if ($openEncounter)
            <div class="flex flex-wrap items-center gap-2">
                @if ($chip['type'])
                    <x-filament::badge color="primary">{{ $chip['type'] }}</x-filament::badge>
                @endif
                @if ($chip['status'])
                    <x-filament::badge :color="$chip['status_color']">{{ $chip['status'] }}</x-filament::badge>
                @endif
                @if ($chip['ward'] || $chip['bed'])
                    <x-filament::badge color="gray">
                        {{ collect([$chip['ward'], $chip['bed']])->filter()->implode(' · ') }}
                    </x-filament::badge>
                @endif
                @if ($chip['los'])
                    <x-filament::badge color="info">LOS {{ $chip['los'] }}</x-filament::badge>
                @endif
            </div>
        @endif
    </div>

    @if (! $openEncounter)
        <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Admit Patient</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Create a new inpatient encounter to admit the patient to a ward.
            </p>
            {{ $this->adtTransferInForm }}
            <div class="flex justify-end">
                <x-filament::button wire:click="transferIn" color="success" icon="heroicon-m-arrow-left-end-on-rectangle">
                    Admit Patient
                </x-filament::button>
            </div>
        </div>
    @else
        @if ($openEncounter->type?->isInpatient() && blank($openEncounter->bed_id))
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Admit / Assign bed</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Assign a ward and bed. Planned inpatient encounters are marked Arrived on assignment.
                </p>
                {{ $this->adtAdmitForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="admitToBed" color="success" icon="heroicon-m-arrow-right-start-on-rectangle">
                        Admit / Assign bed
                    </x-filament::button>
                </div>
            </div>
        @elseif (filled($openEncounter->bed_id))
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Internal transfer</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Move the patient to another ward or bed. The encounter continues.
                </p>
                {{ $this->adtTransferInternalForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="transferInternal" color="info" icon="heroicon-m-arrows-right-left">
                        Transfer internally
                    </x-filament::button>
                </div>
            </div>

            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Transfer out</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    End this encounter and send the patient to another branch or external facility.
                </p>
                {{ $this->adtTransferOutForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="transferOut" color="warning" icon="heroicon-m-building-office-2">
                        Transfer out
                    </x-filament::button>
                </div>
            </div>
        @elseif (! $openEncounter->type?->isInpatient())
            <x-filament::badge color="info">
                Bed assignment and internal transfer are available on inpatient encounters. Create an inpatient visit or use Admit from transfer when no encounter is open.
            </x-filament::badge>
        @endif

        <div class="space-y-3 rounded-xl border border-danger-200 dark:border-danger-900/40 p-4">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Discharge</h4>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Complete the encounter, free the bed, and trigger billing finalization.
            </p>
            {{ $this->dischargeForm }}
            <div class="flex justify-end">
                <x-filament::button wire:click="saveDischarge" color="danger" icon="heroicon-m-arrow-right-on-rectangle">
                    Discharge patient
                </x-filament::button>
            </div>
        </div>
    @endif
</div>
