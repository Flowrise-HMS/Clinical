{{-- Thin progress strip while the server renders the newly selected tab. --}}
<div wire:loading.flex wire:target="activeTab" class="items-center gap-2 mb-3 text-xs text-gray-500 dark:text-gray-400" role="status" aria-live="polite">
    <x-filament::loading-indicator class="h-4 w-4" />
    <span>{{ __('Loading…') }}</span>
</div>
