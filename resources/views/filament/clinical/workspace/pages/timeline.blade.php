<x-filament-panels::page class="p-0 bg-gray-50 dark:bg-gray-950">
    <div>
        @if($currentPatient)
            @php
                $eventCounts = $this->getEventCounts();
                $filters = [
                    'all' => 'All',
                    'encounter' => 'Encounters',
                    'vitals' => 'Vitals',
                    'note' => 'Notes',
                    'order' => 'Orders',
                    'appointment' => 'Appointments',
                ];
            @endphp

            <div class="mb-8">
                <x-filament::tabs>
                    @foreach($filters as $filterKey => $filterLabel)
                        <x-filament::tabs.item
                            :active="$activeFilter === $filterKey"
                            wire:click="setFilter('{{ $filterKey }}')"
                            wire:key="timeline-filter-{{ $filterKey }}"
                        >
                            {{ $filterLabel }}
                            <x-slot name="badge">{{ $eventCounts[$filterKey] ?? 0 }}</x-slot>
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>

            @if($this->getTimelineEvents()->isNotEmpty())
                <div
                    class="mx-auto max-w-3xl px-4"
                    x-data="{
                        observer: null,
                        loadTimer: null,
                        init() {
                            this.observer = new IntersectionObserver((entries) => {
                                entries.forEach((entry) => {
                                    if (!entry.isIntersecting) {
                                        return;
                                    }

                                    clearTimeout(this.loadTimer);
                                    this.loadTimer = setTimeout(() => {
                                        if (!$wire.hasMoreEvents || $wire.isLoadingMore) {
                                            return;
                                        }

                                        $wire.loadMoreEvents();
                                    }, 300);
                                });
                            }, { rootMargin: '200px 0px 200px 0px' });

                            if (this.$refs.loadMoreSentinel) {
                                this.observer.observe(this.$refs.loadMoreSentinel);
                            }
                        },
                    }"
                >
                    @php
                        $events = $this->getTimelineEvents();

                        $styleMap = [
                            'encounter' => ['dot' => 'bg-emerald-500', 'card' => 'border-l-emerald-500', 'iconWrap' => 'bg-emerald-100 dark:bg-emerald-900/40', 'icon' => 'text-emerald-600 dark:text-emerald-400'],
                            'vitals' => ['dot' => 'bg-pink-500', 'card' => 'border-l-pink-500', 'iconWrap' => 'bg-pink-100 dark:bg-pink-900/40', 'icon' => 'text-pink-600 dark:text-pink-400'],
                            'note' => ['dot' => 'bg-amber-500', 'card' => 'border-l-amber-500', 'iconWrap' => 'bg-amber-100 dark:bg-amber-900/40', 'icon' => 'text-amber-600 dark:text-amber-400'],
                            'order' => ['dot' => 'bg-blue-500', 'card' => 'border-l-blue-500', 'iconWrap' => 'bg-blue-100 dark:bg-blue-900/40', 'icon' => 'text-blue-600 dark:text-blue-400'],
                            'appointment' => ['dot' => 'bg-indigo-500', 'card' => 'border-l-indigo-500', 'iconWrap' => 'bg-indigo-100 dark:bg-indigo-900/40', 'icon' => 'text-indigo-600 dark:text-indigo-400'],
                            'other' => ['dot' => 'bg-gray-500', 'card' => 'border-l-gray-500', 'iconWrap' => 'bg-gray-100 dark:bg-gray-800', 'icon' => 'text-gray-600 dark:text-gray-300'],
                        ];

                        $toCarbon = fn ($value) => $value instanceof \Carbon\CarbonInterface
                            ? $value
                            : \Illuminate\Support\Carbon::parse($value);
                    @endphp

                    <div class="relative space-y-4 pl-10 sm:pl-12">
                        <div class="absolute bottom-0 left-3.5 top-0 w-0.5 bg-gray-200 dark:bg-gray-700 sm:left-4"></div>

                        @foreach($events as $event)
                            @php
                                $type = $event['type'] ?? 'other';
                                $style = $styleMap[$type] ?? $styleMap['other'];
                                $hasMetadata = ! empty($event['metadata']);
                                $hasCreator = ! empty($event['creator']);
                                $occurredAt = $toCarbon($event['occurred_at']);
                            @endphp

                            <article
                                wire:key="timeline-event-{{ $event['id'] }}"
                                class="relative"
                                x-data="{ expanded: false }"
                            >
                                <div class="absolute left-[-1.65rem] top-5 sm:left-[-1.85rem]">
                                    <div class="h-3.5 w-3.5 rounded-full {{ $style['dot'] }} ring-4 ring-gray-50 dark:ring-gray-950"></div>
                                </div>

                                <div @class([
                                    'rounded-xl border border-l-4 bg-white p-4 shadow-sm dark:bg-gray-900 sm:p-5',
                                    $style['card'],
                                    'border-gray-200 dark:border-gray-800' => empty($event['is_critical']),
                                    'border-red-300 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20' => ! empty($event['is_critical']),
                                ])>
                                    <div class="flex items-start gap-3">
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $style['iconWrap'] }}">
                                            <x-dynamic-component :component="$event['icon'] ?? 'heroicon-o-clock'" class="h-5 w-5 {{ $style['icon'] }}" />
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $event['title'] }}</h3>
                                                <x-filament::badge color="primary">{{ ucfirst($type) }}</x-filament::badge>
                                                @if(! empty($event['is_critical']))
                                                    <x-filament::badge color="danger">Critical</x-filament::badge>
                                                @endif
                                            </div>

                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event['description'] }}</p>

                                            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                @if($hasCreator)
                                                    <span>{{ $event['creator'] }}</span>
                                                    <span class="opacity-50">•</span>
                                                @endif
                                                <time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('M j, Y g:i A') }}</time>
                                            </div>

                                            @if(! empty($event['url']))
                                                <div class="mt-2">
                                                    <a href="{{ $event['url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                                        {{ __('Open record') }}
                                                    </a>
                                                </div>
                                            @endif

                                            @if($hasMetadata)
                                                <div class="mt-3">
                                                    <button
                                                        type="button"
                                                        x-on:click="expanded = !expanded"
                                                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                                    >
                                                        <span x-text="expanded ? 'Hide details' : 'Show details'"></span>
                                                        <x-heroicon-o-chevron-down class="h-3.5 w-3.5 transition-transform duration-200" x-bind:class="{ 'rotate-180': expanded }" />
                                                    </button>

                                                    <div
                                                        x-show="expanded"
                                                        x-cloak
                                                        x-transition:enter="transition ease-out duration-200"
                                                        x-transition:enter-start="opacity-0 -translate-y-1"
                                                        x-transition:enter-end="opacity-100 translate-y-0"
                                                        x-transition:leave="transition ease-in duration-150"
                                                        x-transition:leave-start="opacity-100 translate-y-0"
                                                        x-transition:leave-end="opacity-0 -translate-y-1"
                                                        class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700"
                                                    >
                                                        <div class="grid grid-cols-1 gap-y-1 text-xs sm:grid-cols-2 sm:gap-x-4">
                                                            @foreach($event['metadata'] as $key => $value)
                                                                @if(! empty($value))
                                                                    <div class="dark:text-gray-300">
                                                                        <span class="font-medium dark:text-gray-400">{{ $key }}:</span>
                                                                        <span>{{ $value }}</span>
                                                                    </div>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="pb-2" x-ref="loadMoreSentinel" aria-hidden="true"></div>

                    @if($this->isLoadingMore)
                        <div class="py-4 text-center">
                            <p class="text-xs text-gray-400">Loading more events...</p>
                        </div>
                    @elseif($this->hasMoreEvents)
                        <div class="py-4 text-center">
                            <p class="text-xs text-gray-400">Scroll to load more events</p>
                        </div>
                    @else
                        <div class="py-4 text-center">
                            <p class="text-xs text-gray-400">No more events</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="mx-auto max-w-2xl rounded-3xl p-20 text-center dark:bg-gray-900">
                    <x-heroicon-o-clock class="mx-auto mb-6 h-24 w-24 text-gray-300" />
                    <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Events</h2>
                    <p class="mt-3 text-gray-500">
                        @if($activeFilter === 'all')
                            No events found for this patient.
                        @else
                            No {{ $activeFilter }} events found for this patient.
                        @endif
                    </p>
                </div>
            @endif
        @else
            <div class="mx-auto max-w-2xl rounded-3xl p-20 text-center dark:bg-gray-900">
                <x-heroicon-o-user-circle class="mx-auto mb-6 h-24 w-24 text-gray-300" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Patient Selected</h2>
                <p class="mt-3 text-gray-500">Please select a patient from the workspace to view their timeline.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
