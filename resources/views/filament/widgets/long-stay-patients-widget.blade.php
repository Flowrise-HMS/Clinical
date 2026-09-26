<x-filament-widgets::widget>
    <x-filament::section heading="Long stays ({{ $encounters->count() }})" description="Over {{ $this->thresholdDays() }} days or past expected discharge">
        <div wire:poll.60s="load">
            @if ($encounters->isNotEmpty())
                <div class="space-y-2">
                    @foreach ($encounters as $encounter)
                        <x-core::patient-link :href="\Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace::getUrl(['patientId' => $encounter->patient_id])" class="block rounded-lg border border-warning-200 p-3 transition-colors hover:bg-warning-100/50 dark:border-warning-500/30 dark:bg-white/5 dark:hover:bg-warning-500/20">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full border border-warning-200 bg-warning-100 dark:border-warning-500/30 dark:bg-warning-500/20">
                                    <span class="text-sm font-bold tabular-nums text-warning-700 dark:text-warning-400">{{ $encounter->los_days ?? '—' }}d</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $encounter->patient?->full_name ?? $encounter->encounter_number }}</p>
                                    <p class="text-xs font-medium text-warning-700 dark:text-warning-400/80">
                                        {{ $encounter->location?->name ?? 'No ward' }}{{ $encounter->bed?->name ? ' · '.$encounter->bed->name : '' }}
                                        @if ($encounter->expected_discharge_at)
                                            · expected {{ $encounter->expected_discharge_at->format('j M') }}
                                        @endif
                                    </p>
                                </div>
                                <x-heroicon-m-chevron-right x-show="! opening" class="h-4 w-4 text-warning-300 dark:text-warning-500/50" />
                            </div>
                        </x-core::patient-link>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-6">
                    <p class="text-center text-sm font-medium text-success-700 dark:text-success-400">
                        <x-heroicon-o-check-circle class="mx-auto mb-2 h-5 w-5 text-success-600 dark:text-success-400" />
                        No long stays
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
