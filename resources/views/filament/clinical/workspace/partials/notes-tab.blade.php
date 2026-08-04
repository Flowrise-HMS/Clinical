<div class="space-y-4">
    {{ $this->noteForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="saveClinicalNote" color="primary" icon="heroicon-m-check">
            Save Note
        </x-filament::button>
    </div>
</div>
