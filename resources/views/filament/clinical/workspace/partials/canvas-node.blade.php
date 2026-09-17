{{-- Rendered inside `<template x-for="node in visible">` — one root element. --}}
<div
    class="absolute"
    x-bind:style="`left: ${position(node.id).x}px; top: ${position(node.id).y}px`"
    x-bind:data-node-id="node.id"
    x-init="observe(node.id, $el)"
>
    {{-- Patient / encounter (root-level cards) --}}
    <template x-if="node.kind === 'patient' || node.kind === 'encounter'">
        <div
            class="rounded-xl border bg-white shadow-sm dark:bg-gray-900"
            style="width: 280px"
            x-bind:class="[
                node.kind === 'patient' ? 'border-primary-300 ring-1 ring-primary-200 dark:border-primary-700 dark:ring-primary-900' : (node.isActive ? 'border-emerald-300 dark:border-emerald-800' : 'border-gray-200 dark:border-gray-800'),
                isSelected(node.id) ? 'ring-2 ring-primary-500 shadow-md' : '',
                connectFrom === node.id ? 'ring-2 ring-primary-400 ring-dashed' : '',
                drag?.id === node.id ? 'shadow-lg cursor-grabbing' : 'cursor-grab',
            ]"
            x-on:pointerdown.stop="onItemPointerDown($event, node.id, 'node')"
            x-on:dblclick.stop="focusNode(node.id)"
        >
            <div class="flex items-start gap-2.5 p-3">
                <div class="canvas-icon flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" x-bind:class="node.kind === 'patient' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : typeClass('encounter', 'iconWrap')" x-html="icons[node.kind === 'patient' ? 'patient' : 'encounter']"></div>
                <div class="min-w-0 flex-1">
                    <h3 class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-bind:title="node.title" x-text="node.title"></h3>
                    <p class="truncate text-[11px] text-gray-500 dark:text-gray-400" x-text="node.subtitle"></p>
                    <div class="mt-1.5 flex flex-wrap gap-1" x-show="(node.badges ?? []).length">
                        <template x-for="badge in node.badges ?? []" :key="badge.label">
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-medium" x-bind:class="badgeClass(badge.color)" x-text="badge.label"></span>
                        </template>
                    </div>
                </div>
                <div class="flex shrink-0 flex-col items-end gap-1">
                    <template x-if="hasChildren(node)">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                            x-bind:title="isCollapsed(node.id) ? 'Show branch' : 'Hide branch'"
                            x-on:pointerdown.stop
                            x-on:click.stop="toggleCollapse(node.id)"
                        >
                            <span x-text="node.count ?? node.children.length"></span>
                            <x-heroicon-m-chevron-right class="h-3.5 w-3.5 transition-transform" x-bind:class="!isCollapsed(node.id) && 'rotate-90'" />
                        </button>
                    </template>
                    <template x-if="Object.keys(node.metadata ?? {}).length || node.url">
                        <button type="button" class="rounded p-0.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200" title="Details" x-on:pointerdown.stop x-on:click.stop="toggleExpanded(node.id)">
                            <x-heroicon-m-information-circle class="h-4 w-4" />
                        </button>
                    </template>
                </div>
            </div>
            <div x-show="isExpanded(node.id)" x-cloak class="border-t border-gray-100 px-3 pb-3 pt-2 text-xs dark:border-gray-800">
                <dl class="grid grid-cols-1 gap-y-0.5">
                    <template x-for="[key, value] in Object.entries(node.metadata ?? {})" :key="key">
                        <div class="flex gap-1 text-[11px]">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400" x-text="key + ':'"></dt>
                            <dd class="min-w-0 truncate text-gray-700 dark:text-gray-200" x-bind:title="value" x-text="value"></dd>
                        </div>
                    </template>
                </dl>
                <template x-if="node.url">
                    <button type="button" class="mt-2 text-[11px] font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" x-on:pointerdown.stop x-on:click.stop="openUrl(node)">{{ __('Open record') }}</button>
                </template>
            </div>
        </div>
    </template>

    {{-- Group pill --}}
    <template x-if="node.kind === 'group'">
        <button
            type="button"
            class="flex items-center gap-2 rounded-full border bg-white py-1.5 pl-1.5 pr-3 text-left shadow-sm dark:bg-gray-900"
            style="width: 220px"
            x-bind:class="[
                isSelected(node.id) ? 'border-primary-400 ring-2 ring-primary-500' : 'border-gray-200 dark:border-gray-800',
                connectFrom === node.id ? 'ring-2 ring-primary-400 ring-dashed' : '',
            ]"
            x-on:pointerdown.stop="onItemPointerDown($event, node.id, 'node')"
            x-on:click.stop="if (!drag?.moved) toggleCollapse(node.id)"
        >
            <span class="canvas-icon flex h-7 w-7 shrink-0 items-center justify-center rounded-full" x-bind:class="typeClass(node.type, 'iconWrap')" x-html="icons[node.type] ?? icons.other"></span>
            <span class="min-w-0 flex-1 truncate text-xs font-semibold text-gray-800 dark:text-gray-100" x-text="node.title"></span>
            <span class="rounded-full bg-gray-100 px-1.5 text-[10px] font-semibold tabular-nums text-gray-600 dark:bg-gray-800 dark:text-gray-300" x-text="node.count"></span>
            <x-heroicon-m-chevron-right class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform" x-bind:class="!isCollapsed(node.id) && 'rotate-90'" />
        </button>
    </template>

    {{-- Activity item --}}
    <template x-if="node.kind === 'item'">
        <article
            class="rounded-xl border border-l-4 bg-white shadow-sm dark:bg-gray-900"
            style="width: 280px"
            x-bind:class="[
                typeClass(node.type, 'accent'),
                node.isCritical ? 'border-red-300 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20' : 'border-gray-200 dark:border-gray-800',
                isSelected(node.id) ? 'ring-2 ring-primary-500 shadow-md' : '',
                connectFrom === node.id ? 'ring-2 ring-primary-400 ring-dashed' : '',
                drag?.id === node.id ? 'shadow-lg cursor-grabbing' : 'cursor-grab',
            ]"
            x-on:pointerdown.stop="onItemPointerDown($event, node.id, 'node')"
            x-on:click.stop="if (!drag?.moved && (node.description || Object.keys(node.metadata ?? {}).length || node.url)) toggleExpanded(node.id)"
        >
            <div class="flex items-start gap-2.5 p-2.5">
                <span class="canvas-icon flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" x-bind:class="typeClass(node.type, 'iconWrap')" x-html="icons[node.type] ?? icons.other"></span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-1.5">
                        <h4 class="truncate text-xs font-semibold text-gray-900 dark:text-white" x-bind:title="node.title" x-text="node.title"></h4>
                        <template x-if="hasChildren(node)">
                            <button type="button" class="inline-flex shrink-0 items-center gap-0.5 rounded px-1 text-[10px] font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" x-bind:title="isCollapsed(node.id) ? 'Show items' : 'Hide items'" x-on:pointerdown.stop x-on:click.stop="toggleCollapse(node.id)">
                                <span x-text="node.children.length"></span>
                                <x-heroicon-m-chevron-right class="h-3 w-3 transition-transform" x-bind:class="!isCollapsed(node.id) && 'rotate-90'" />
                            </button>
                        </template>
                    </div>
                    <p class="truncate text-[11px] text-gray-500 dark:text-gray-400" x-show="node.subtitle" x-text="node.subtitle"></p>
                    <div class="mt-1 flex flex-wrap items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400">
                        <span x-show="node.timeLabel" x-text="node.timeLabel"></span>
                        <template x-for="badge in node.badges ?? []" :key="badge.label">
                            <span class="rounded-full px-1.5 py-0.5 font-medium" x-bind:class="badgeClass(badge.color)" x-text="badge.label"></span>
                        </template>
                        <span class="rounded-full bg-danger-100 px-1.5 py-0.5 font-medium text-danger-700 dark:bg-danger-900/40 dark:text-danger-300" x-show="node.isCritical">Critical</span>
                    </div>
                </div>
            </div>
            <div x-show="isExpanded(node.id)" x-cloak class="border-t border-gray-100 px-2.5 pb-2.5 pt-2 text-xs dark:border-gray-800">
                <p class="text-gray-600 dark:text-gray-300" x-show="node.description" x-text="node.description"></p>
                <dl class="mt-1.5 grid grid-cols-1 gap-y-0.5" x-show="Object.keys(node.metadata ?? {}).length">
                    <template x-for="[key, value] in Object.entries(node.metadata ?? {})" :key="key">
                        <div class="flex gap-1 text-[11px]">
                            <dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400" x-text="key + ':'"></dt>
                            <dd class="min-w-0 truncate text-gray-700 dark:text-gray-200" x-bind:title="value" x-text="value"></dd>
                        </div>
                    </template>
                </dl>
                <template x-if="node.url">
                    <button type="button" class="mt-2 text-[11px] font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" x-on:pointerdown.stop x-on:click.stop="openUrl(node)">{{ __('Open record') }}</button>
                </template>
            </div>
        </article>
    </template>

    {{-- Medication (prescription) card --}}
    <template x-if="node.kind === 'medication'">
        <article
            class="rounded-xl border border-l-4 bg-white shadow-sm dark:bg-gray-900"
            style="width: 280px"
            x-bind:class="[
                node.prn ? 'border-l-violet-500' : (node.nextDueStatus === 'overdue' ? 'border-l-danger-500' : (node.nextDueStatus === 'due_now' || node.nextDueStatus === 'due_soon' ? 'border-l-warning-500' : 'border-l-violet-500')),
                node.isTerminal ? 'opacity-70' : '',
                isSelected(node.id) ? 'ring-2 ring-primary-500 shadow-md' : 'border-gray-200 dark:border-gray-800',
                connectFrom === node.id ? 'ring-2 ring-primary-400 ring-dashed' : '',
                drag?.id === node.id ? 'shadow-lg cursor-grabbing' : 'cursor-grab',
            ]"
            x-on:pointerdown.stop="onItemPointerDown($event, node.id, 'node')"
        >
            <div class="p-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h4 class="truncate text-sm font-semibold text-gray-900 dark:text-white" x-bind:title="node.title" x-text="node.title"></h4>
                        <p class="truncate text-[11px] text-gray-500 dark:text-gray-400" x-text="node.subtitle"></p>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <template x-if="hasChildren(node)">
                            <button type="button" class="inline-flex items-center gap-0.5 rounded px-1 text-[10px] font-medium text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" x-bind:title="isCollapsed(node.id) ? 'Show doses' : 'Hide doses'" x-on:pointerdown.stop x-on:click.stop="toggleCollapse(node.id)">
                                <span x-text="node.children.length + ' doses'"></span>
                                <x-heroicon-m-chevron-right class="h-3 w-3 transition-transform" x-bind:class="!isCollapsed(node.id) && 'rotate-90'" />
                            </button>
                        </template>
                        <div class="flex gap-1">
                            <span class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300" x-show="node.prn">PRN</span>
                            <span class="rounded-full bg-danger-100 px-1.5 py-0.5 text-[10px] font-semibold text-danger-700 dark:bg-danger-900/40 dark:text-danger-300" x-show="node.isControlled">Controlled</span>
                            <span class="rounded-full bg-warning-100 px-1.5 py-0.5 text-[10px] font-semibold text-warning-700 dark:bg-warning-900/40 dark:text-warning-300" x-show="node.financialHold">Hold</span>
                        </div>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between gap-2 text-[11px]">
                    <span class="text-gray-600 dark:text-gray-300">
                        <span class="font-semibold tabular-nums" x-text="node.givenCount"></span><span x-text="node.totalAdministrations ? ' / ' + node.totalAdministrations : ''"></span>
                        <span class="text-gray-400"> given</span>
                    </span>
                    <span class="text-gray-500 dark:text-gray-400" x-text="node.statusLabel"></span>
                </div>

                <template x-if="node.nextDueAt">
                    <div
                        class="mt-2 rounded-lg px-2 py-1.5 text-[11px]"
                        x-bind:class="{
                            'bg-danger-50 text-danger-700 dark:bg-danger-950/40 dark:text-danger-300': node.nextDueStatus === 'overdue',
                            'bg-warning-50 text-warning-800 dark:bg-warning-950/40 dark:text-warning-300': node.nextDueStatus === 'due_now' || node.nextDueStatus === 'due_soon',
                            'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-200': !['overdue', 'due_now', 'due_soon'].includes(node.nextDueStatus),
                        }"
                    >
                        <span class="font-medium">Next dose</span>
                        <span x-text="node.nextDueLabel"></span>
                        <span class="opacity-70" x-text="'(' + relativeLabel(node.nextDueAt) + ')'"></span>
                    </div>
                </template>
                <template x-if="!node.nextDueAt && !node.prn && node.isTerminal">
                    <div class="mt-2 rounded-lg bg-gray-50 px-2 py-1.5 text-[11px] text-gray-500 dark:bg-gray-800 dark:text-gray-400">Course complete</div>
                </template>

                <template x-if="node.canRecord && (node.prn || node.nextDueAt)">
                    <button
                        type="button"
                        class="mt-2 inline-flex w-full items-center justify-center gap-1 rounded-lg bg-success-600 px-2 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-success-500"
                        x-on:pointerdown.stop
                        x-on:click.stop="recordDose(node)"
                    >
                        <x-heroicon-m-beaker class="h-3.5 w-3.5" />
                        Record dose
                    </button>
                </template>
            </div>
        </article>
    </template>

    {{-- Dose slot chip --}}
    <template x-if="node.kind === 'dose'">
        <button
            type="button"
            class="relative flex flex-col items-center justify-center rounded-lg border text-[11px] leading-tight shadow-sm"
            style="width: 72px; height: 44px"
            x-bind:class="[slotClass(node.status), node.actionable ? 'cursor-pointer ring-2 ring-primary-400 ring-offset-1 dark:ring-offset-gray-950' : 'cursor-default']"
            x-bind:title="doseTitle(node)"
            x-on:pointerdown.stop
            x-on:click.stop="recordDose(node)"
        >
            <span class="font-semibold tabular-nums" x-text="node.dueAtLabel"></span>
            <span class="opacity-80" x-text="node.subtitle"></span>
            <template x-if="node.isNextDue">
                <span class="absolute -top-2 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-1 text-[9px] font-semibold text-white dark:bg-white dark:text-gray-900">next</span>
            </template>
        </button>
    </template>
</div>
