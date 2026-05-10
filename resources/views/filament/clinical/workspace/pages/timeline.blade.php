<x-filament-panels::page class="p-0 bg-gray-50 dark:bg-gray-950">
    <div>
        @if($currentPatient)
            @php
                $eventCounts = $this->getEventCounts();
            @endphp

            <div class="mb-8">
                <x-filament::tabs x-data="{ activeTab: '{{ $activeFilter }}' }">
                    <x-filament::tabs.item
                        alpine-active="activeTab === 'all'"
                        x-on:click="activeTab = 'all'; window.location = '{{ URL::current() }}?filter=all'"
                    >
                        All
                        <x-slot name="badge">{{ $eventCounts['all'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'encounter'"
                        x-on:click="activeTab = 'encounter'; window.location = '{{ URL::current() }}?filter=encounter'"
                    >
                        Encounters
                        <x-slot name="badge">{{ $eventCounts['encounter'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'vitals'"
                        x-on:click="activeTab = 'vitals'; window.location = '{{ URL::current() }}?filter=vitals'"
                    >
                        Vitals
                        <x-slot name="badge">{{ $eventCounts['vitals'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'note'"
                        x-on:click="activeTab = 'note'; window.location = '{{ URL::current() }}?filter=note'"
                    >
                        Notes
                        <x-slot name="badge">{{ $eventCounts['note'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'order'"
                        x-on:click="activeTab = 'order'; window.location = '{{ URL::current() }}?filter=order'"
                    >
                        Orders
                        <x-slot name="badge">{{ $eventCounts['order'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'appointment'"
                        x-on:click="activeTab = 'appointment'; window.location = '{{ URL::current() }}?filter=appointment'"
                    >
                        Appointments
                        <x-slot name="badge">{{ $eventCounts['appointment'] }}</x-slot>
                    </x-filament::tabs.item>
                </x-filament::tabs>
            </div>

            @if($this->getTimelineEvents()->isNotEmpty())
                <div
                    class="max-w-5xl mx-auto px-4"
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
                        $isAllFilter = $activeFilter === 'all';

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

                    <div class="relative pl-12 sm:pl-16 md:pl-0">
                        <div class="absolute left-4 sm:left-6 md:left-1/2 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700 md:-translate-x-1/2"></div>

                        @if($isAllFilter)
                            @php
                                $preferredTypeOrder = ['appointment', 'note', 'encounter', 'order', 'vitals'];
                                $groupedEvents = $events
                                    ->groupBy(fn ($event) => $event['type'] ?? 'other')
                                    ->map(function ($items, $type) use ($toCarbon) {
                                        $sorted = collect($items)
                                            ->sortByDesc(fn ($item) => $toCarbon($item['occurred_at'])->timestamp)
                                            ->values();

                                        return [
                                            'type' => $type,
                                            'events' => $sorted,
                                            'latest_at' => $toCarbon($sorted->first()['occurred_at']),
                                        ];
                                    })
                                    ->sortByDesc(fn ($group) => $group['latest_at']->timestamp)
                                    ->values();

                                $groupTypeOrder = collect($groupedEvents)
                                    ->pluck('type')
                                    ->sortBy(function ($type) use ($preferredTypeOrder) {
                                        $position = array_search($type, $preferredTypeOrder, true);

                                        return $position === false ? (count($preferredTypeOrder) + 100) : $position;
                                    })
                                    ->values()
                                    ->all();
                            @endphp

                            @foreach($groupedEvents as $group)
                                @php
                                    $type = $group['type'];
                                    $style = $styleMap[$type] ?? $styleMap['other'];
                                    $typePosition = array_search($type, $groupTypeOrder, true);
                                    $isLeft = $typePosition === false ? true : ($typePosition % 2 === 0);
                                    $latestAt = $group['latest_at'];
                                    $groupLabel = match($type) {
                                        'encounter' => 'Encounters',
                                        'vitals' => 'Vitals',
                                        'note' => 'Notes',
                                        'order' => 'Orders',
                                        'appointment' => 'Appointments',
                                        default => ucfirst($type),
                                    };
                                    $headerEvent = $group['events']->first();
                                    $childEvents = $group['events']->slice(1)->values();
                                @endphp

                                <section class="relative mb-8" x-data="{ open: true }">
                                    <div class="{{ $isLeft ? 'md:col-start-1' : 'md:col-start-3' }}">
                                        <article class="relative mb-3 md:grid md:grid-cols-[1fr_3rem_1fr] md:gap-6" x-data="{ expanded: false }">
                                            <div class="{{ $isLeft ? 'md:col-start-1' : 'md:col-start-3' }}">
                                                <x-filament::section class="relative border-l-4 {{ $style['card'] }} {{ $isLeft ? 'md:text-right' : '' }}">
                                                    <div class="hidden md:block absolute top-8 {{ $isLeft ? 'right-[-1.65rem]' : 'left-[-1.65rem]' }} w-6 h-0.5 bg-gray-300 dark:bg-gray-600"></div>
                                                    <div class="hidden md:block absolute top-[1.625rem] {{ $isLeft ? 'right-[-1.5rem]' : 'left-[-1.5rem]' }}">
                                                        @if($isLeft)
                                                            <x-heroicon-o-chevron-right class="w-3 h-3 text-gray-500 dark:text-gray-400" />
                                                        @else
                                                            <x-heroicon-o-chevron-left class="w-3 h-3 text-gray-500 dark:text-gray-400" />
                                                        @endif
                                                    </div>

                                                    <div class="flex items-start gap-3 {{ $isLeft ? 'md:flex-row-reverse' : '' }}">
                                                        <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $style['iconWrap'] }}">
                                                            <x-dynamic-component :component="$headerEvent['icon'] ?? 'heroicon-o-clock'" class="w-5 h-5 {{ $style['icon'] }}" />
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-start justify-between gap-2 {{ $isLeft ? 'md:flex-row-reverse' : '' }}">
                                                                <div class="min-w-0">
                                                                    <h3 class="text-sm font-semibold">{{ $groupLabel }}</h3>
                                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                                        {{ $group['events']->count() }} event(s) • Latest {{ $latestAt->format('M j, Y g:i A') }}
                                                                    </p>
                                                                </div>
                                                                <button
                                                                    type="button"
                                                                    x-on:click="open = !open"
                                                                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                                                >
                                                                    <span x-text="open ? 'Collapse' : 'Expand'"></span>
                                                                    <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform duration-200" x-bind:class="{ 'rotate-180': !open }" />
                                                                </button>
                                                            </div>

                                                            <div class="mt-2 flex flex-wrap items-center gap-2 {{ $isLeft ? 'md:justify-end' : '' }}">
                                                                <h4 class="font-semibold text-sm text-gray-900 dark:text-white">{{ $headerEvent['title'] }}</h4>
                                                                @if(!empty($headerEvent['is_critical']))
                                                                    <x-filament::badge color="danger">Critical</x-filament::badge>
                                                                @endif
                                                            </div>
                                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $headerEvent['description'] }}</p>
                                                            @if(! empty($headerEvent['url']))
                                                                <div class="mt-2 {{ $isLeft ? 'md:text-right' : '' }}">
                                                                    <a href="{{ $headerEvent['url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Open record') }}</a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </x-filament::section>
                                            </div>

                                            <div class="absolute left-[-2.2rem] sm:left-[-2.7rem] top-[1.65rem] md:static md:col-start-2 md:flex md:justify-center md:pt-7">
                                                <div class="w-3.5 h-3.5 rounded-full {{ $style['dot'] }} ring-4 ring-gray-50 dark:ring-gray-950"></div>
                                            </div>
                                        </article>

                                        <div x-show="open" x-transition class="relative mt-2 ml-6 sm:ml-10 space-y-2">
                                            <div class="absolute top-0 bottom-0 left-0 w-px bg-gray-300 dark:bg-gray-600"></div>

                                            @foreach($childEvents as $event)
                                                @php
                                                    $hasMetadata = !empty($event['metadata']);
                                                    $hasCreator = !empty($event['creator']);
                                                    $occurredAt = $toCarbon($event['occurred_at']);
                                                @endphp

                                                <div class="flex items-center justify-center">
                                                    <article class="relative ml-4" x-data="{ expanded: false }">
                                                        <div class="absolute left-[-0.35rem] top-[1.1rem]">
                                                            <div class="w-1.5 h-1.5 rounded-full {{ $style['dot'] }} ring-2 ring-gray-50 dark:ring-gray-950"></div>
                                                        </div>
                                                        <div class="absolute left-[-0.35rem] top-[1.1rem] bottom-[-0.5rem] w-px bg-gray-300 dark:bg-gray-600"></div>

                                                        <x-filament::section compact="false" class="!p-2.5 !text-xs">
                                                            <div class="flex items-start gap-2">
                                                                <div class="w-6 h-6 rounded-md flex items-center justify-center {{ $style['iconWrap'] }} shrink-0">
                                                                    <x-dynamic-component :component="$event['icon'] ?? 'heroicon-o-clock'" class="w-3 h-3 {{ $style['icon'] }}" />
                                                                </div>
                                                                <div class="min-w-0 flex-1">
                                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                                        <h4 class="font-medium text-xs text-gray-900 dark:text-white">{{ $event['title'] }}</h4>
                                                                        @if(!empty($event['is_critical']))
                                                                            <x-filament::badge color="danger" size="xs">Critical</x-filament::badge>
                                                                        @endif
                                                                    </div>
                                                                    <p class="mt-0.5 text-2xs text-gray-600 dark:text-gray-300">{{ $event['description'] }}</p>
                                                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-2xs text-gray-500 dark:text-gray-400">
                                                                        @if($hasCreator)
                                                                            <span>{{ $event['creator'] }}</span>
                                                                            <span class="opacity-50">•</span>
                                                                        @endif
                                                                        <time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('M j, Y g:i A') }}</time>
                                                                    </div>
                                                                    @if(! empty($event['url']))
                                                                        <div class="mt-1">
                                                                            <a href="{{ $event['url'] }}" class="text-2xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Open record') }}</a>
                                                                        </div>
                                                                    @endif

                                                                    @if($hasMetadata)
                                                                        <div class="mt-1.5">
                                                                            <button
                                                                                type="button"
                                                                                x-on:click="expanded = !expanded"
                                                                                class="inline-flex items-center gap-1 text-2xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300"
                                                                            >
                                                                                <span x-text="expanded ? 'Hide' : 'Details'"></span>
                                                                                <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform duration-200" x-bind:class="{ 'rotate-180': expanded }" />
                                                                            </button>

                                                                            <div x-show="expanded" x-transition class="mt-1.5 border-t border-gray-200 pt-1.5 dark:border-gray-700">
                                                                                <div class="grid grid-cols-1 gap-y-1 text-2xs sm:grid-cols-2 sm:gap-x-3">
                                                                                    @foreach($event['metadata'] as $key => $value)
                                                                                        @if(!empty($value))
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
                                                        </x-filament::section>
                                                    </article>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </section>
                            @endforeach
                        @else
                            @foreach($events as $event)
                                @php
                                    $type = $event['type'] ?? 'other';
                                    $style = $styleMap[$type] ?? $styleMap['other'];
                                    $hasMetadata = !empty($event['metadata']);
                                    $hasCreator = !empty($event['creator']);
                                    $occurredAt = $toCarbon($event['occurred_at']);
                                    $isLeft = $loop->odd;
                                @endphp
                                <article class="relative mt-5 md:grid md:grid-cols-[1fr_3rem_1fr] md:gap-6" x-data="{ expanded: false }">
                                    <div class="{{ $isLeft ? 'md:col-start-1' : 'md:col-start-3' }}">
                                        <div class="relative rounded-xl border border-l-4 {{ $style['card'] }} {{ !empty($event['is_critical']) ? 'border-red-300 bg-red-50/60 dark:border-red-900 dark:bg-red-950/20' : 'border-gray-200 dark:border-gray-800' }} p-4 sm:p-5 shadow-sm {{ $isLeft ? 'md:text-right' : '' }}">
                                            <div class="hidden md:block absolute top-8 {{ $isLeft ? 'right-[-1.65rem]' : 'left-[-1.65rem]' }} w-6 h-0.5 bg-gray-300 dark:bg-gray-600"></div>
                                            <div class="hidden md:block absolute top-[1.625rem] {{ $isLeft ? 'right-[-1.5rem]' : 'left-[-1.5rem]' }}">
                                                @if($isLeft)
                                                    <x-heroicon-o-chevron-right class="w-3 h-3 text-gray-500 dark:text-gray-400" />
                                                @else
                                                    <x-heroicon-o-chevron-left class="w-3 h-3 text-gray-500 dark:text-gray-400" />
                                                @endif
                                            </div>

                                            <div class="flex items-start gap-3 {{ $isLeft ? 'md:flex-row-reverse' : '' }}">
                                                <div class="w-9 h-9 rounded-lg flex items-center justify-center {{ $style['iconWrap'] }}">
                                                    <x-dynamic-component :component="$event['icon'] ?? 'heroicon-o-clock'" class="w-5 h-5 {{ $style['icon'] }}" />
                                                </div>
                                                <div class="">
                                                    <div class="flex flex-wrap items-center gap-2 {{ $isLeft ? 'md:justify-end' : '' }}">
                                                        <h3 class="font-semibold text-sm text-gray-900 dark:text-white">{{ $event['title'] }}</h3>
                                                        <x-filament::badge color="primary">{{ ucfirst($type) }}</x-filament::badge>
                                                        @if(!empty($event['is_critical']))
                                                            <x-filament::badge color="danger">Critical</x-filament::badge>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event['description'] }}</p>
                                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400 {{ $isLeft ? 'md:justify-end' : '' }}">
                                                        @if($hasCreator)
                                                            <span>{{ $event['creator'] }}</span>
                                                            <span class="opacity-50">•</span>
                                                        @endif
                                                        <time datetime="{{ $occurredAt->toIso8601String() }}">{{ $occurredAt->format('M j, Y g:i A') }}</time>
                                                    </div>
                                                    @if(! empty($event['url']))
                                                        <div class="mt-2 {{ $isLeft ? 'md:text-right' : '' }}">
                                                            <a href="{{ $event['url'] }}" class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Open record') }}</a>
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
                                                                <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform duration-200" x-bind:class="{ 'rotate-180': expanded }" />
                                                            </button>

                                                            <div
                                                                x-show="expanded"
                                                                x-transition:enter="transition ease-out duration-200"
                                                                x-transition:enter-start="opacity-0 -translate-y-1"
                                                                x-transition:enter-end="opacity-100 translate-y-0"
                                                                x-transition:leave="transition ease-in duration-150"
                                                                x-transition:leave-start="opacity-100 translate-y-0"
                                                                x-transition:leave-end="opacity-0 -translate-y-1"
                                                                class="mt-3 border-t border-gray-200 pt-3 dark:border-gray-700"
                                                            >
                                                                <div class="grid grid-cols-1 gap-y-1 text-xs sm:grid-cols-2 sm:gap-x-4 {{ $isLeft ? 'md:text-right' : '' }}">
                                                                    @foreach($event['metadata'] as $key => $value)
                                                                        @if(!empty($value))
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
                                    </div>

                                    <div class="absolute left-[-2.2rem] sm:left-[-2.7rem] top-[1.65rem] md:static md:col-start-2 md:flex md:justify-center md:pt-7">
                                        <div class="w-3.5 h-3.5 rounded-full {{ $style['dot'] }} ring-4 ring-gray-50 dark:ring-gray-950"></div>
                                    </div>
                                </article>
                            @endforeach
                        @endif
                    </div>

                    <div class="pb-2" x-ref="loadMoreSentinel" aria-hidden="true"></div>

                    @if($this->isLoadingMore)
                        <div class="text-center py-4">
                            <p class="text-xs text-gray-400">Loading more events...</p>
                        </div>
                    @elseif($this->hasMoreEvents)
                        <div class="text-center py-4">
                            <p class="text-xs text-gray-400">Scroll to load more events</p>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <p class="text-xs text-gray-400">No more events</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="dark:bg-gray-900 rounded-3xl p-20 text-center max-w-2xl mx-auto">
                    <x-heroicon-o-clock class="w-24 h-24 mx-auto text-gray-300 mb-6" />
                    <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Events</h2>
                    <p class="text-gray-500 mt-3">
                        @if($activeFilter === 'all')
                            No events found for this patient.
                        @else
                            No {{ $activeFilter }} events found for this patient.
                        @endif
                    </p>
                </div>
            @endif
        @else
            <div class="dark:bg-gray-900 rounded-3xl p-20 text-center max-w-2xl mx-auto">
                <x-heroicon-o-user-circle class="w-24 h-24 mx-auto text-gray-300 mb-6" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Patient Selected</h2>
                <p class="text-gray-500 mt-3">Please select a patient from the workspace to view their timeline.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
