<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <label for="notes-ward-select" class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Ward:
        </label>
        <select
            id="notes-ward-select"
            wire:model.live="wardId"
            class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
        >
            <option value="">All Wards</option>
            @foreach ($wardOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
