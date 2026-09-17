{{--
    Shared interactive canvas root. Alpine (clinical-canvas.js) lays out the
    node tree and renders every node, connector and note; Blade never loops
    nodes, so the root is `wire:ignore` and Livewire updates arrive through
    `$wire.$watch('canvasTree')`.

    Expects: $canvasKey, $tree (root node), $meta, $layout, $readOnly.
--}}
@php
    /*
     * Class maps live here (not in the JS) so the Core theme's Tailwind scan
     * picks them up. Keep the type palette in step with timeline.blade.php.
     */
    $canvasClasses = [
        'type' => [
            'encounter' => ['accent' => 'border-l-emerald-500', 'iconWrap' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'],
            'vitals' => ['accent' => 'border-l-pink-500', 'iconWrap' => 'bg-pink-100 text-pink-700 dark:bg-pink-900/40 dark:text-pink-300'],
            'note' => ['accent' => 'border-l-amber-500', 'iconWrap' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'],
            'order' => ['accent' => 'border-l-blue-500', 'iconWrap' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'],
            'request_item' => ['accent' => 'border-l-blue-300', 'iconWrap' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-300'],
            'diagnosis' => ['accent' => 'border-l-rose-500', 'iconWrap' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300'],
            'medication' => ['accent' => 'border-l-violet-500', 'iconWrap' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300'],
            'adt' => ['accent' => 'border-l-cyan-500', 'iconWrap' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/40 dark:text-cyan-300'],
            'appointment' => ['accent' => 'border-l-indigo-500', 'iconWrap' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300'],
            'allergy' => ['accent' => 'border-l-red-500', 'iconWrap' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'],
            'other' => ['accent' => 'border-l-gray-400', 'iconWrap' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
        ],
        'badge' => [
            'gray' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
            'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300',
            'success' => 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300',
            'warning' => 'bg-warning-100 text-warning-700 dark:bg-warning-900/40 dark:text-warning-300',
            'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300',
            'info' => 'bg-info-100 text-info-700 dark:bg-info-900/40 dark:text-info-300',
        ],
        'slot' => [
            'given' => 'bg-success-500 text-white border-success-600',
            'omitted' => 'bg-gray-400 text-white border-gray-500',
            'refused' => 'bg-gray-500 text-white border-gray-600 line-through',
            'due_soon' => 'bg-warning-100 text-warning-800 border-warning-400 dark:bg-warning-900/40 dark:text-warning-200',
            'due_now' => 'bg-warning-500 text-white border-warning-600 animate-pulse',
            'overdue' => 'bg-danger-500 text-white border-danger-600',
            'upcoming' => 'bg-white text-gray-700 border-gray-300 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-600',
        ],
        'note' => [
            'amber' => 'bg-amber-100 border-amber-300 text-amber-950 dark:bg-amber-900/60 dark:border-amber-700 dark:text-amber-50',
            'sky' => 'bg-sky-100 border-sky-300 text-sky-950 dark:bg-sky-900/60 dark:border-sky-700 dark:text-sky-50',
            'emerald' => 'bg-emerald-100 border-emerald-300 text-emerald-950 dark:bg-emerald-900/60 dark:border-emerald-700 dark:text-emerald-50',
            'rose' => 'bg-rose-100 border-rose-300 text-rose-950 dark:bg-rose-900/60 dark:border-rose-700 dark:text-rose-50',
            'violet' => 'bg-violet-100 border-violet-300 text-violet-950 dark:bg-violet-900/60 dark:border-violet-700 dark:text-violet-50',
        ],
    ];

    $canvasIcons = collect([
        'patient' => 'heroicon-o-user',
        'encounter' => 'heroicon-o-clipboard-document-list',
        'vitals' => 'heroicon-o-heart',
        'note' => 'heroicon-o-document-text',
        'order' => 'heroicon-o-arrow-up-circle',
        'request_item' => 'heroicon-o-beaker',
        'diagnosis' => 'heroicon-o-tag',
        'medication' => 'heroicon-o-beaker',
        'adt' => 'heroicon-o-building-office-2',
        'appointment' => 'heroicon-o-calendar-days',
        'allergy' => 'heroicon-o-exclamation-triangle',
        'other' => 'heroicon-o-square-3-stack-3d',
    ])->map(fn (string $icon): string => svg($icon, 'h-4 w-4')->toHtml())->all();

    $canvasConfig = [
        'classes' => $canvasClasses,
        'icons' => $canvasIcons,
        'canvasKey' => $canvasKey,
        'root' => $tree,
        'meta' => $meta,
        'layout' => $layout,
        'readOnly' => $readOnly ?? false,
    ];
@endphp

<style>
    [data-clinical-canvas] { touch-action: none; user-select: none; }
    [data-clinical-canvas].canvas-surface {
        background-image: radial-gradient(circle, rgb(209 213 219) 1px, transparent 1px);
        background-size: 24px 24px;
    }
    .dark [data-clinical-canvas].canvas-surface {
        background-image: radial-gradient(circle, rgb(55 65 81) 1px, transparent 1px);
    }
    [data-clinical-canvas] textarea, [data-clinical-canvas] input { user-select: text; }
    [data-clinical-canvas] .canvas-edge { pointer-events: stroke; cursor: pointer; }
    [data-clinical-canvas] .canvas-icon > svg { width: 1rem; height: 1rem; }
</style>

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('clinical-canvas', 'clinical') }}"
    x-data="clinicalCanvas(@js($canvasConfig))"
    wire:ignore
    data-clinical-canvas
    tabindex="0"
    x-on:wheel.prevent="onWheel($event)"
    x-on:pointerdown="onBackgroundPointerDown($event)"
    x-on:pointermove.window="onPointerMove($event)"
    x-on:pointerup.window="onPointerUp($event)"
    x-on:pointercancel.window="onPointerUp($event)"
    x-on:keydown="onKeyDown($event)"
    x-on:keyup="onKeyUp($event)"
    x-on:contextmenu.prevent
    x-bind:class="cursorClass()"
    class="canvas-surface relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-gray-800 dark:bg-gray-950"
    style="height: calc(100vh - 14rem); min-height: 32rem;"
>
    @include('clinical::clinical.workspace.partials.canvas-toolbar')

    {{-- World --}}
    <div class="absolute inset-0 origin-top-left" x-bind:style="`transform: ${worldTransform()}`">
        {{-- Grid blocks behind dose slots --}}
        <template x-for="(block, id) in gridBlocks" :key="'grid-' + id">
            <div class="absolute rounded-xl border border-dashed border-gray-300 bg-white/40 dark:border-gray-700 dark:bg-gray-900/40" x-bind:style="`left: ${block.x - 6}px; top: ${block.y - 6}px; width: ${block.w + 12}px; height: ${block.h + 12}px`"></div>
        </template>

        {{-- Tree connectors. `<template>` is not an HTML template inside <svg>, so each edge gets its own svg. --}}
        <template x-for="edge in edges" :key="edge.id">
            <svg class="pointer-events-none absolute left-0 top-0 overflow-visible" style="width: 1px; height: 1px;">
                <path class="fill-none stroke-gray-300 dark:stroke-gray-600" stroke-width="2" x-bind:d="treeEdgePath(edge)"></path>
            </svg>
        </template>

        {{-- User connectors --}}
        <template x-for="edge in visibleUserEdges()" :key="edge.id">
            <svg class="pointer-events-none absolute left-0 top-0 overflow-visible" style="width: 1px; height: 1px;">
                <path
                    class="canvas-edge fill-none"
                    stroke-dasharray="7 5"
                    x-bind:d="edgePath(edge)"
                    x-bind:stroke="isSelected(edge.id) ? 'rgb(var(--primary-500))' : 'rgb(var(--primary-400))'"
                    x-bind:stroke-width="isSelected(edge.id) ? 3 : 2"
                    x-on:pointerdown.stop="selection = { type: 'edge', id: edge.id }"
                ></path>
            </svg>
        </template>
        <svg class="pointer-events-none absolute left-0 top-0 overflow-visible" style="width: 1px; height: 1px;" x-show="connectFrom">
            <path class="fill-none stroke-primary-500" stroke-width="2" stroke-dasharray="6 4" x-bind:d="draftPath()"></path>
        </svg>

        <template x-for="edge in visibleUserEdges()" :key="'label-' + edge.id">
            <div class="absolute -translate-x-1/2 -translate-y-1/2" x-bind:style="`left: ${edgeMidpoint(edge).x}px; top: ${edgeMidpoint(edge).y}px`" x-show="isSelected(edge.id) || edge.label">
                <template x-if="isSelected(edge.id) && !readOnly">
                    <div class="flex items-center gap-1 rounded-md bg-white p-1 shadow ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700" x-on:pointerdown.stop>
                        <input type="text" class="w-32 rounded border-0 bg-transparent px-1 text-xs text-gray-800 focus:ring-0 dark:text-gray-100" placeholder="Label" maxlength="120" x-bind:value="edge.label" x-on:input="setEdgeLabel(edge.id, $event.target.value)" />
                        <button type="button" class="rounded p-0.5 text-gray-400 hover:text-danger-600" title="Remove connector" x-on:click="removeEdge(edge.id)">
                            <x-heroicon-m-x-mark class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
                <template x-if="!isSelected(edge.id) && edge.label">
                    <span class="rounded bg-white/90 px-1.5 py-0.5 text-[11px] text-gray-700 ring-1 ring-gray-200 dark:bg-gray-900/90 dark:text-gray-200 dark:ring-gray-700" x-text="edge.label"></span>
                </template>
            </div>
        </template>

        {{-- Nodes --}}
        <template x-for="node in visible" :key="node.id">
            @include('clinical::clinical.workspace.partials.canvas-node')
        </template>

        {{-- Sticky notes --}}
        <template x-for="note in layout.notes" :key="note.id">
            @include('clinical::clinical.workspace.partials.canvas-note')
        </template>
    </div>

    <div class="pointer-events-none absolute bottom-3 left-3 z-10 hidden flex-wrap gap-2 rounded-lg bg-white/90 px-2.5 py-1.5 text-[11px] text-gray-600 shadow ring-1 ring-gray-200 sm:flex dark:bg-gray-900/90 dark:text-gray-300 dark:ring-gray-700">
        <span>Scroll to pan · Ctrl+scroll to zoom · drag a card to nudge it · click a chevron to fold a branch</span>
    </div>
</div>
