{{--
    Tab bar shared by every role view. `$tabs` is the role's tab list, `$rounded` the corner classes.

    The highlight follows Alpine's `tab` so it moves on click, before the server has rendered the panel.
    The object syntax is required: a string `x-bind:class` never removes server-rendered classes, which
    leaves the previous tab highlighted until the response arrives.
--}}
@php
    $activeClasses = 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/40 dark:border-gray-700/40 font-semibold scale-[1.02]';
    $inactiveClasses = 'font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-white/40 dark:hover:bg-gray-800/40';
@endphp
<div role="tablist"
    class="flex gap-1 min-w-max p-1 bg-gray-100/80 dark:bg-gray-900/60 border border-gray-200/50 dark:border-gray-800/50 {{ $rounded ?? 'rounded-xl' }} backdrop-blur-sm shadow-inner"
    x-data="{
        tab: $wire.$entangle('activeTab', true),
        {{-- Scroll the bar only (not the page) so the active tab is visible on deep links and narrow screens. --}}
        reveal() {
            const button = this.$root.querySelector(`[data-tab='${this.tab}']`), bar = this.$root.parentElement
            if (! button) return
            const b = button.getBoundingClientRect(), c = bar.getBoundingClientRect()
            if (b.left < c.left || b.right > c.right) bar.scrollLeft += b.left - c.left - (c.width - b.width) / 2
        },
    }"
    x-init="$nextTick(() => reveal()); $watch('tab', () => $nextTick(() => reveal()))">
    @foreach ($tabs as $tabKey => $tab)
        <button type="button" role="tab" wire:key="workspace-tab-{{ $tabKey }}" data-tab="{{ $tabKey }}"
            x-on:click="tab = @js($tabKey)"
            aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
            x-bind:aria-selected="(tab === @js($tabKey)).toString()"
            @class([
                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm transition-all duration-200 whitespace-nowrap',
                $activeClasses => $activeTab === $tabKey,
                $inactiveClasses => $activeTab !== $tabKey,
            ])
            x-bind:class="{ @js($activeClasses): tab === @js($tabKey), @js($inactiveClasses): tab !== @js($tabKey) }">
            @isset($tab['icon'])
                <x-filament::icon :icon="$tab['icon']" class="w-4 h-4" />
            @endisset
            <span>{{ $tab['label'] }}</span>
        </button>
    @endforeach
</div>
