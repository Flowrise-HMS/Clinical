<div class="space-y-3">
    <div>
        <input
            type="text"
            wire:model.debounce.400ms="searchTerm"
            placeholder="Search ICD-11 (e.g. cholera)..."
            class="fi-input block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition duration-75 placeholder:text-gray-400 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400"
        >
    </div>

    @if (count($breadcrumbs))
        <nav class="flex flex-wrap items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
            <button type="button" wire:click="goToRoot" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                ICD-11
            </button>
            @foreach ($breadcrumbs as $index => $label)
                <span>/</span>
                <button
                    type="button"
                    wire:click="goToBreadcrumb({{ $index }})"
                    class="max-w-[16rem] truncate hover:underline"
                >
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    @endif

    <div class="max-h-80 divide-y divide-gray-100 overflow-y-auto rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-600">
        @forelse ($entries as $entry)
            <div class="flex items-center justify-between gap-2 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    @if ($entry['code'])
                        <x-filament::badge color="primary">{{ $entry['code'] }}</x-filament::badge>
                    @endif
                    <span class="truncate text-sm text-gray-800 dark:text-gray-200">{{ $entry['label'] }}</span>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    @if ($entry['has_children'])
                        <x-filament::button
                            size="xs"
                            color="gray"
                            icon="heroicon-m-chevron-right"
                            wire:click="drill('{{ $entry['uri'] }}', '{{ addslashes($entry['label']) }}')"
                        >
                            Browse
                        </x-filament::button>
                    @endif
                    @if ($entry['uri'])
                        <x-filament::button
                            size="xs"
                            color="primary"
                            icon="heroicon-m-plus"
                            wire:click="selectEntry('{{ $entry['uri'] }}')"
                        >
                            Select
                        </x-filament::button>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                No ICD entries available.
            </div>
        @endforelse
    </div>
</div>
