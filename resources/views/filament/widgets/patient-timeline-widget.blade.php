<x-filament-widgets::widget>
    <x-filament::section heading="Clinical Timeline" icon="heroicon-m-clock">
        {{-- Widget content --}}
        <div class="rounded-xl p-6">
            @if($this->canView())
                {{-- Timeline --}}
                @if($events->isNotEmpty())
                    <div class="relative">
                        {{-- Vertical Line --}}
                        <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gradient-to-b from-primary-500 via-primary-400 to-gray-200 rounded-full"></div>

                        {{-- Events --}}
                        <div class="space-y-4">
                            @foreach($events as $event)
                                <div class="relative flex items-start gap-4 group"
                                    x-data="{ expanded: false }">

                                    {{-- Timeline Node --}}
                                    <div class="relative z-10 flex-shrink-0 w-12 h-12 rounded-full border-4
                                                flex items-center justify-center shadow-sm
                                                @switch($event['type'])
                                                    @case('vitals') border-danger-400 @break
                                                    @case('note') border-info-400 @break
                                                    @case('order') border-warning-400 @break
                                                    @case('task') border-success-400 @break
                                                    @case('medication') border-purple-400 @break
                                                    @default border-gray-300
                                                @endswitch
                                                group-hover:scale-110 transition-transform cursor-pointer">
                                        <x-dynamic-component :component="$event['icon']"
                                            :class="'w-5 h-5 ' . match($event['type']) {
                                                'vitals'     => 'text-danger-500',
                                                'note'       => 'text-info-500',
                                                'order'      => 'text-warning-500',
                                                'task'       => 'text-success-500',
                                                'medication' => 'text-purple-500',
                                                default      => 'text-gray-500',
                                            }" />
                                    </div>

                                    {{-- Event Content --}}
                                    <div class="flex-1 min-w-0 rounded-lg border border-gray-200 p-4
                                                group-hover:border-primary-300 group-hover:shadow-md transition-all">

                                        <div class="flex items-start justify-between gap-4">
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="font-medium text-gray-900">{{ $event['title'] }}</span>
                                                    @if($event['is_critical'] ?? false)
                                                        <span class="px-1.5 py-0.5 bg-danger-100 text-danger-700 text-xs rounded font-semibold uppercase tracking-wide">
                                                            Critical
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-sm text-gray-600 mb-2">{{ $event['description'] }}</p>

                                                @if($event['metadata'])
                                                    <div class="bg-gray-50 rounded-lg p-3 text-sm font-mono border border-gray-100">
                                                        @foreach($event['metadata'] as $key => $value)
                                                            <div class="flex justify-between gap-6 py-0.5">
                                                                <span class="text-gray-500">{{ $key }}:</span>
                                                                <span class="text-gray-900 font-semibold">{{ is_array($value) ? implode(', ', $value) : $value }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if($event['creator'])
                                                    <p class="text-xs text-gray-400 mt-2">
                                                        by {{ $event['creator'] }}
                                                    </p>
                                                @endif
                                            </div>

                                            <div class="text-right flex-shrink-0">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ optional(\Carbon\Carbon::parse($event['occurred_at'])->format('h:i A')) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ optional(\Carbon\Carbon::parse($event['occurred_at'])->format('M d, Y')) }}
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Actions (appear on hover) --}}
                                        <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-100
                                                    opacity-0 group-hover:opacity-100 transition-opacity">
                                            <x-filament::button size="xs" color="gray" icon="heroicon-m-eye" class="text-gray-600">
                                                View Details
                                            </x-filament::button>
                                            @if($event['is_editable'] ?? false)
                                                <x-filament::button size="xs" color="gray" icon="heroicon-m-pencil" class="text-gray-600">
                                                    Edit
                                                </x-filament::button>
                                            @endif
                                            @if(($event['has_result'] ?? false) || ($event['type'] ?? '') === 'order')
                                                <x-filament::button size="xs" color="info" icon="heroicon-m-arrow-down-circle" class="text-info-600">
                                                    View Result
                                                </x-filament::button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    {{-- Empty State --}}
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center">
                            <x-heroicon-m-clipboard-document-list class="w-8 h-8 text-gray-400" />
                        </div>
                        <p class="text-gray-500">No clinical events recorded yet</p>
                        <p class="text-sm text-gray-400 mt-1">Events will appear here as clinical documentation is added</p>
                    </div>
                @endif
            @else
                {{-- No Patient Selected --}}
                <div class="text-center py-12">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                        <x-heroicon-m-user-plus class="w-8 h-8 text-gray-400" />
                    </div>
                    <p class="text-gray-500">Select a patient to view timeline</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

