<x-filament-widgets::widget>
    @php($cards = $this->getWardCards())

    <x-filament::section heading="Ward Incharge Overview" description="Live operational snapshot for wards under your charge">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach ($cards as $card)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                {{ $card['name'] }}
                                @if ($card['code'])
                                    <span class="text-xs font-normal text-gray-500">({{ $card['code'] }})</span>
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $card['occupied'] }} / {{ $card['beds_total'] }} beds occupied ({{ $card['occupancy_pct'] }}%)
                                · {{ $card['available'] }} available
                                @if ($card['blocked'] > 0)
                                    · <span class="font-medium text-danger-600">{{ $card['blocked'] }} blocked</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <x-filament::button tag="a" :href="$card['ward_board_url']" size="xs" color="primary">
                                Ward Board
                            </x-filament::button>
                            <x-filament::button tag="a" :href="$card['statement_url']" size="xs" color="gray">
                                Daily Statement
                            </x-filament::button>
                            <x-filament::button tag="a" :href="$card['notes_url']" size="xs" color="gray">
                                Notes
                            </x-filament::button>
                        </div>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Daily Statement</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $card['statement_status'] }}
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Requisitions</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $card['inventory']['pending_requisitions'] }} open
                                @if ($card['inventory']['awaiting_endorsement'] > 0)
                                    <span class="block text-xs font-medium text-warning-600">
                                        {{ $card['inventory']['awaiting_endorsement'] }} to endorse
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Broken Items</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $card['inventory']['open_issues_count'] }} open
                                @if ($card['inventory']['critical_issues_count'] > 0)
                                    <span class="block text-xs font-medium text-danger-600">
                                        {{ $card['inventory']['critical_issues_count'] }} high/critical
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-800/60">
                            <div class="text-xs text-gray-500 dark:text-gray-400">Handover / Stock</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $card['unacknowledged_notes'] }} unread
                                @if ($card['inventory']['low_stock_count'] > 0)
                                    <span class="block text-xs font-medium text-warning-600">
                                        {{ $card['inventory']['low_stock_count'] }} low stock
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if (! empty($card['pinned_notes']))
                        <div class="mt-3 space-y-1 border-t border-gray-100 pt-2 dark:border-gray-800">
                            @foreach ($card['pinned_notes'] as $note)
                                <div class="text-xs text-gray-700 dark:text-gray-300">
                                    <span class="font-semibold text-primary-600">Pinned:</span> {{ $note['title'] }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
