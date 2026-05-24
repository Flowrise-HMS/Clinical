<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Patient History</h3>

    @php $pastEncounters = $this->pastEncounters; @endphp

    @forelse($pastEncounters as $encounter)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
            {{-- Encounter Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800/80 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-medium text-sm text-gray-900 dark:text-white truncate">{{ $encounter['encounter_number'] }}</span>
                    <span class="text-xs text-gray-500">{{ $encounter['type'] }}</span>
                    <x-filament::badge :color="$encounter['status_color'] ?? 'gray'" class="text-xs">
                        {{ $encounter['status'] ?? 'N/A' }}
                    </x-filament::badge>
                    @if ($encounter['coverage'] ?? null)
                        <x-filament::badge :color="$encounter['coverage_color'] ?? 'gray'" class="text-xs">
                            {{ $encounter['coverage'] }}
                        </x-filament::badge>
                    @endif
                </div>
                <span class="text-xs text-gray-400 shrink-0">{{ $encounter['date'] }}</span>
            </div>

            <div class="px-4 py-3 space-y-3">
                {{-- Vitals --}}
                @if ($encounter['vitals'] ?? null)
                    <div class="flex flex-wrap gap-3 text-sm">
                        @if ($encounter['vitals']['bp'])
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">BP</span>
                                {{ $encounter['vitals']['bp'] }}
                            </span>
                        @endif
                        @if ($encounter['vitals']['hr'])
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">HR</span>
                                {{ $encounter['vitals']['hr'] }} bpm
                            </span>
                        @endif
                        @if ($encounter['vitals']['temp'])
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">Temp</span>
                                {{ $encounter['vitals']['temp'] }}°C
                            </span>
                        @endif
                        @if ($encounter['vitals']['spo2'])
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">SpO₂</span>
                                {{ $encounter['vitals']['spo2'] }}%
                            </span>
                        @endif
                        @if ($encounter['vitals']['rr'])
                            <span class="text-gray-600 dark:text-gray-400">
                                <span class="font-medium text-gray-900 dark:text-white">RR</span>
                                {{ $encounter['vitals']['rr'] }} /min
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Diagnoses --}}
                @if (count($encounter['diagnoses']) > 0)
                    <div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($encounter['diagnoses'] as $dx)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-700">
                                    @if ($dx['code'])
                                        <x-filament::badge>{{ $dx['code'] }}</x-filament::badge>
                                    @endif
                                    {{ $dx['label'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Medications --}}
                @if (count($encounter['medications']) > 0)
                    <div class="text-sm space-y-1">
                        <span class="text-xs font-medium text-gray-500 uppercase tracking-wide">Medications</span>
                        @foreach ($encounter['medications'] as $med)
                            <div class="text-gray-700 dark:text-gray-300">
                                {{ $med['name'] }}
                                @if ($med['dosage'])
                                    <span class="text-gray-500">{{ $med['dosage'] }}</span>
                                @endif
                                @if ($med['frequency'])
                                    <span class="text-gray-500">{{ $med['frequency'] }}</span>
                                @endif
                                @if ($med['route'])
                                    <span class="text-gray-400">({{ $med['route'] }})</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Note preview --}}
                @if ($encounter['note_preview'] ?? null)
                    <div class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                        {{ Str::limit($encounter['note_preview'], 200) }}
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <x-filament::icon name="heroicon-o-clock" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-3" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No previous encounter history found.</p>
        </div>
    @endforelse
</div>
