<x-filament-panels::page>
    <x-filament::tabs>
        <x-filament::tabs.item icon="heroicon-m-squares-2x2"
            :active="$viewMode === 'card'"
            wire:click="toggleViewMode('card')">
            Cards
        </x-filament::tabs.item>

        <x-filament::tabs.item icon="heroicon-m-table-cells"
            :active="$viewMode === 'table'"
            wire:click="toggleViewMode('table')">
            Table
        </x-filament::tabs.item>

    </x-filament::tabs>
    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
