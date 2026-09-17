@php
    $toolbarButton = 'inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 disabled:opacity-40 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-white';
@endphp

<div
    class="absolute left-3 top-3 z-20 flex items-center gap-0.5 rounded-lg bg-white/95 p-1 shadow ring-1 ring-gray-200 dark:bg-gray-900/95 dark:ring-gray-700"
    x-on:pointerdown.stop
    x-on:wheel.stop
>
    <button type="button" class="{{ $toolbarButton }}" title="Fit to view (F)" x-on:click="fitToView()">
        <x-heroicon-m-arrows-pointing-in class="h-4 w-4" />
    </button>
    <button type="button" class="{{ $toolbarButton }}" title="Zoom out (-)" x-on:click="zoomOut()">
        <x-heroicon-m-minus class="h-4 w-4" />
    </button>
    <button type="button" style="min-width: 3.25rem" class="h-8 rounded-md px-1 text-xs tabular-nums text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800" title="Reset zoom (0)" x-on:click="resetZoom()" x-text="zoomPercent()"></button>
    <button type="button" class="{{ $toolbarButton }}" title="Zoom in (+)" x-on:click="zoomIn()">
        <x-heroicon-m-plus class="h-4 w-4" />
    </button>

    <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

    <button type="button" class="{{ $toolbarButton }}" title="Expand all branches" x-on:click="expandAll()">
        <x-heroicon-m-arrows-pointing-out class="h-4 w-4" />
    </button>
    <button type="button" class="{{ $toolbarButton }}" title="Collapse all branches" x-on:click="collapseAll()">
        <x-heroicon-m-bars-3-bottom-left class="h-4 w-4" />
    </button>

    <template x-if="!readOnly">
        <div class="flex items-center gap-0.5">
            <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>
            <button type="button" class="{{ $toolbarButton }}" x-bind:class="mode === 'note' && 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'" title="Add sticky note (N) — click on the canvas to place" x-on:click="toggleMode('note')">
                <x-heroicon-m-document-text class="h-4 w-4" />
            </button>
            <button type="button" class="{{ $toolbarButton }}" x-bind:class="mode === 'connect' && 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300'" title="Connect two items (C) — click the first, then the second" x-on:click="toggleMode('connect')">
                <x-heroicon-m-arrow-long-right class="h-4 w-4" />
            </button>
            <button type="button" class="{{ $toolbarButton }}" title="Reset layout to default" x-on:click="if (confirm('Reset this canvas to its default layout? Sticky notes and connectors will be removed.')) resetLayout()">
                <x-heroicon-m-arrow-path class="h-4 w-4" />
            </button>

            <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

            <span class="flex h-8 items-center px-1.5 text-[11px] text-gray-500 dark:text-gray-400" x-text="{ idle: '', dirty: 'Unsaved', saving: 'Saving…', saved: 'Saved', error: 'Save failed' }[saveState]"
                  x-bind:class="saveState === 'error' && 'text-danger-600 dark:text-danger-400'"></span>
        </div>
    </template>

    <template x-if="mode === 'connect'">
        <span class="ml-1 whitespace-nowrap text-[11px] text-primary-700 dark:text-primary-300" x-text="connectFrom ? 'Now click the target item' : 'Click the first item'"></span>
    </template>
    <template x-if="mode === 'note'">
        <span class="ml-1 whitespace-nowrap text-[11px] text-primary-700 dark:text-primary-300">Click on the canvas to place a note</span>
    </template>
</div>
