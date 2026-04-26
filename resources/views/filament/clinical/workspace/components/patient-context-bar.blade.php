<div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm sticky top-0 z-50 backdrop-blur-md">
    <div class="max-w-6xl mx-auto px-6 py-5">
        <div class="flex items-center justify-between gap-6">

            {{-- Patient Info Section --}}
            <div class="flex items-center gap-5 flex-1 min-w-0">

                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-2xl overflow-hidden bg-gray-100 dark:bg-gray-800 ring-2 ring-white dark:ring-gray-700 shadow-sm">
                        @if($patient?->hasPhoto())
                            <img src="{{ private_url($patient->photo) }}"
                                 alt="{{ $patient->full_name }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-2xl font-semibold text-gray-500 dark:text-gray-400">
                                {{ substr($patient->first_name ?? 'P', 0, 1) }}{{ substr($patient->last_name ?? '', 0, 1) }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Name & Basic Info --}}
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white truncate">
                            {{ $patient->full_name }}
                        </h2>

                        @if($patient->is_deceased ?? false)
                            <span class="px-3 py-1 text-xs font-medium bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-400 rounded-full">
                                Deceased
                            </span>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 mt-0.5">
                        <span>{{ $patient->mrn ?? '—' }}</span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span>{{ $patient->age ?? 'N/A' }} years</span>
                        <span class="text-gray-300 dark:text-gray-600">•</span>
                        <span>{{ $patient->gender?->getLabel() ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            {{-- Encounter Type --}}
            @if($encounter)
                <div class="flex-shrink-0">
                    <span class="inline-flex px-4 py-1.5 text-sm font-medium rounded-2xl
                        @if($encounter->type?->value === 'emergency')
                            bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-400
                        @elseif($encounter->type?->value === 'inpatient')
                            bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400
                        @else
                            bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-400
                        @endif">
                        {{ $encounter->type?->getLabel() ?? 'Outpatient' }}
                    </span>
                </div>
            @endif

            {{-- Allergy Alert --}}
            @if($patient->allergies?->isNotEmpty())
                <div class="flex-shrink-0">
                    <div
                        class="flex items-center gap-2 bg-red-50 dark:bg-red-950 hover:bg-red-100 dark:hover:bg-red-900 border border-red-200 dark:border-red-800 px-4 py-2.5 rounded-2xl cursor-pointer transition-colors"
                        x-data="{ showAllergies: false }"
                        x-on:click="showAllergies = !showAllergies">
                        <x-heroicon-m-exclamation-triangle class="w-5 h-5 text-red-500 dark:text-red-400" />
                        <span class="text-sm font-semibold text-red-700 dark:text-red-300">
                            {{ $patient->allergies->count() }} Allerg{{ $patient->allergies->count() === 1 ? 'y' : 'ies' }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- Vitals Quick Stats --}}
            @if($latestVitals)
                <div class="hidden xl:flex items-center gap-7 text-sm border-l border-gray-200 dark:border-gray-700 pl-8">
                    @if($latestVitals->blood_pressure)
                        <div class="text-center">
                            <div class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-medium">BP</div>
                            <div class="font-semibold text-gray-900 dark:text-white {{ $latestVitals->isAbnormalBloodPressure() ? 'text-amber-600 dark:text-amber-400' : '' }}">
                                {{ $latestVitals->blood_pressure }}
                            </div>
                        </div>
                    @endif

                    @if($latestVitals->heart_rate)
                        <div class="text-center">
                            <div class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-medium">HR</div>
                            <div class="font-semibold text-gray-900 dark:text-white">{{ $latestVitals->heart_rate }} <span class="text-xs font-normal text-gray-500">bpm</span></div>
                        </div>
                    @endif

                    @if($latestVitals->spo2)
                        <div class="text-center">
                            <div class="text-[10px] uppercase tracking-widest text-gray-500 dark:text-gray-400 font-medium">SpO₂</div>
                            <div class="font-semibold {{ $latestVitals->isLowOxygenSaturation() ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                                {{ $latestVitals->spo2 }}%
                            </div>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    </div>
</div>
