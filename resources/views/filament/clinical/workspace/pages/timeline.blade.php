<x-filament-panels::page class="p-0 bg-gray-50 dark:bg-gray-950">
    <div>
        @if($currentPatient)
            <div class="mb-8">
                <x-filament::tabs x-data="{ activeTab: '{{ $activeFilter }}' }">
                    <x-filament::tabs.item
                        alpine-active="activeTab === 'all'"
                        x-on:click="activeTab = 'all'; window.location = '{{ URL::current() }}?filter=all'"
                    >
                        All
                        <x-slot name="badge">{{ $this->getEventCounts()['all'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'encounter'"
                        x-on:click="activeTab = 'encounter'; window.location = '{{ URL::current() }}?filter=encounter'"
                    >
                        Encounters
                        <x-slot name="badge">{{ $this->getEventCounts()['encounter'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'vitals'"
                        x-on:click="activeTab = 'vitals'; window.location = '{{ URL::current() }}?filter=vitals'"
                    >
                        Vitals
                        <x-slot name="badge">{{ $this->getEventCounts()['vitals'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'note'"
                        x-on:click="activeTab = 'note'; window.location = '{{ URL::current() }}?filter=note'"
                    >
                        Notes
                        <x-slot name="badge">{{ $this->getEventCounts()['note'] }}</x-slot>
                    </x-filament::tabs.item>

                    <x-filament::tabs.item
                        alpine-active="activeTab === 'order'"
                        x-on:click="activeTab = 'order'; window.location = '{{ URL::current() }}?filter=order'"
                    >
                        Orders
                        <x-slot name="badge">{{ $this->getEventCounts()['order'] }}</x-slot>
                    </x-filament::tabs.item>
                </x-filament::tabs>
            </div>

            @if($this->getTimelineEvents()->isNotEmpty())
                <div class="relative">
                    <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>

                    @foreach($this->getTimelineEvents() as $event)
                        @php
                            $type = $event['type'];
                            $dotColor = 'bg-gray-400';
                            $bgColorClass = 'bg-gray-100 text-gray-600';
                            if ($type === 'encounter') { $dotColor = 'bg-emerald-500'; $bgColorClass = 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-400'; }
                            elseif ($type === 'vitals') { $dotColor = 'bg-pink-500'; $bgColorClass = 'bg-pink-100 text-pink-600 dark:bg-pink-900 dark:text-pink-400'; }
                            elseif ($type === 'note') { $dotColor = 'bg-amber-500'; $bgColorClass = 'bg-amber-100 text-amber-600 dark:bg-amber-900 dark:text-amber-400'; }
                            elseif ($type === 'order') { $dotColor = 'bg-blue-500'; $bgColorClass = 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400'; }
                            $hasMetadata = !empty($event['metadata']);
                            $hasCreator = !empty($event['creator']);
                        @endphp
                        <div class="relative pl-16 pb-8 last:pb-0">
                            <div class="absolute left-[22px] top-4 w-3 h-3 rounded-full {{ $dotColor }} ring-4 ring-gray-50 dark:ring-gray-900"></div>

                            <div class="bg-white dark:bg-gray-900 rounded-2xl p-5 shadow-sm border @if($event['is_critical']) border-l-4 border-l-red-500 @else border-gray-100 dark:border-gray-800 @endif">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center {{ $bgColorClass }}">
                                        @if($type === 'encounter')
                                            <x-heroicon-o-user-plus class="w-5 h-5" />
                                        @elseif($type === 'vitals')
                                            <x-heroicon-o-heart class="w-5 h-5" />
                                        @elseif($type === 'note')
                                            <x-heroicon-o-document-text class="w-5 h-5" />
                                        @else
                                            <x-heroicon-o-clipboard-document-list class="w-5 h-5" />
                                        @endif
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ $event['title'] }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-0.5">
                                            {{ $event['description'] }}
                                        </p>
                                        <p class="text-xs text-gray-400 mt-1">
                                            {{ $event['occurred_at']->format('M j, Y g:i A') }}
                                            @if($hasCreator)
                                                - {{ $event['creator'] }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                @if($hasMetadata)
                                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                    <div class="grid grid-cols-2 gap-3">
                                        @foreach($event['metadata'] as $key => $value)
                                            @if(!empty($value))
                                            <div>
                                                <span class="text-xs text-gray-400">{{ $key }}</span>
                                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $value }}</p>
                                            </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="pl-16 pt-4">
                        <p class="text-sm text-gray-400 text-center">Scroll to load more events</p>
                    </div>
                </div>
            @else
                <div class="bg-white dark:bg-gray-900 rounded-3xl p-20 text-center">
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
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-20 text-center">
                <x-heroicon-o-user-circle class="w-24 h-24 mx-auto text-gray-300 mb-6" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No Patient Selected</h2>
                <p class="text-gray-500 mt-3">Please select a patient from the workspace to view their timeline.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
