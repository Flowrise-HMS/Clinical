<x-filament-widgets::widget>
    <x-filament::section heading="Critical Patients ({{ $criticalPatients->count() }})">
        {{-- Outer Container: Uses a subtle background that shifts in dark mode --}}
        <div>
            @if($criticalPatients->isNotEmpty())
                <div class="space-y-2">
                    @foreach($criticalPatients as $patient)
                        <div class="rounded-lg border border-danger-200 dark:border-danger-500/30 dark:bg-white/5 p-3
                                    hover:bg-danger-100/50 dark:hover:bg-danger-500/20 cursor-pointer transition-colors shadow-sm"
                            wire:click="$dispatch('select-patient', { patientId: '{{ $patient->id }}' })">

                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-danger-100 dark:bg-danger-500/20 flex items-center justify-center border border-danger-200 dark:border-danger-500/30">
                                    <span class="text-sm font-bold text-danger-700 dark:text-danger-400">
                                        {{ str($patient->full_name ?? 'U')->substr(0, 2)->upper() }}
                                    </span>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-950 dark:text-white truncate">
                                        {{ $patient->full_name }}
                                    </p>
                                    <p class="text-xs text-danger-600 dark:text-danger-400/80 font-medium">
                                        {{ $patient->latestEncounter?->chief_complaint ?? 'Emergency case' }}
                                    </p>
                                </div>

                                <x-heroicon-m-chevron-right class="w-4 h-4 text-danger-300 dark:text-danger-500/50" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-6">
                    <p class="text-sm text-center font-medium text-success-700 dark:text-success-400"> <span><x-heroicon-o-check-circle class="w-5 h-5 mx-auto text-success-600 dark:text-success-400 mb-2" /></span> No critical patients</p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
