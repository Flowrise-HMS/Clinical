<x-filament-panels::page>
    {{-- Patient Context Header --}}
    {{ $this->infolist() }}

    {{-- Vitals Content --}}
    <div class="p-6 bg-gray-50 min-h-[calc(100vh-8rem)]">
        @if($currentPatient)
            {{-- Current Vitals Card --}}
            @if($latestVitals)
                <div class="rounded-xl border border-gray-200 p-6 mb-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Current Vitals</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Recorded {{ $latestVitals->recorded_at?->diffForHumans() ?? 'Unknown' }}
                    </p>

                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        {{-- Blood Pressure --}}
                        @if($latestVitals->systolic_bp || $latestVitals->diastolic_bp)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">BP</div>
                                <div class="text-2xl font-bold {{ $latestVitals->isAbnormalBloodPressure() ? 'text-warning-600' : 'text-gray-900' }}">
                                    {{ $latestVitals->blood_pressure ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-1">mmHg</div>
                            </div>
                        @endif

                        {{-- Heart Rate --}}
                        @if($latestVitals->heart_rate)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">HR</div>
                                <div class="text-2xl font-bold text-gray-900">{{ $latestVitals->heart_rate }}</div>
                                <div class="text-xs text-gray-400 mt-1">bpm</div>
                            </div>
                        @endif

                        {{-- Temperature --}}
                        @if($latestVitals->temperature)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Temp</div>
                                <div class="text-2xl font-bold text-gray-900">{{ $latestVitals->temperature }}</div>
                                <div class="text-xs text-gray-400 mt-1">°C</div>
                            </div>
                        @endif

                        {{-- SpO2 --}}
                        @if($latestVitals->spo2)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">SpO2</div>
                                <div class="text-2xl font-bold {{ $latestVitals->isLowOxygenSaturation() ? 'text-warning-600' : 'text-gray-900' }}">
                                    {{ $latestVitals->spo2 }}%
                                </div>
                                <div class="text-xs text-gray-400 mt-1">O2 Sat</div>
                            </div>
                        @endif

                        {{-- Respiratory Rate --}}
                        @if($latestVitals->respiratory_rate)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">RR</div>
                                <div class="text-2xl font-bold text-gray-900">{{ $latestVitals->respiratory_rate }}</div>
                                <div class="text-xs text-gray-400 mt-1">/min</div>
                            </div>
                        @endif

                        {{-- Weight/Height/BMI --}}
                        @if($latestVitals->weight || $latestVitals->height)
                            <div class="bg-gray-50 rounded-lg p-4 text-center">
                                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">BMI</div>
                                <div class="text-2xl font-bold text-gray-900">{{ $latestVitals->bmi ?? '—' }}</div>
                                <div class="text-xs text-gray-400 mt-1">{{ $latestVitals->bmi_category ?? '' }}</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Vitals History --}}
            <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Vitals History</h3>

                @if($vitalsHistory->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-xs text-gray-500 uppercase tracking-wide border-b">
                                    <th class="pb-3 pr-4">Date/Time</th>
                                    <th class="pb-3 pr-4">BP</th>
                                    <th class="pb-3 pr-4">HR</th>
                                    <th class="pb-3 pr-4">Temp</th>
                                    <th class="pb-3 pr-4">SpO2</th>
                                    <th class="pb-3 pr-4">RR</th>
                                    <th class="pb-3">Recorded By</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($vitalsHistory as $vital)
                                    <tr class="text-sm hover:bg-gray-50">
                                        <td class="py-3 pr-4 text-gray-900">
                                            {{ $vital->recorded_at?->format('M d, Y H:i') ?? '—' }}
                                        </td>
                                        <td class="py-3 pr-4 {{ $vital->isAbnormalBloodPressure() ? 'text-warning-600 font-medium' : 'text-gray-900' }}">
                                            {{ $vital->blood_pressure ?? '—' }}
                                        </td>
                                        <td class="py-3 pr-4 text-gray-900">
                                            {{ $vital->heart_rate ? $vital->heart_rate.' bpm' : '—' }}
                                        </td>
                                        <td class="py-3 pr-4 text-gray-900">
                                            {{ $vital->temperature ? $vital->temperature.'°C' : '—' }}
                                        </td>
                                        <td class="py-3 pr-4 {{ $vital->isLowOxygenSaturation() ? 'text-warning-600 font-medium' : 'text-gray-900' }}">
                                            {{ $vital->spo2 ? $vital->spo2.'%' : '—' }}
                                        </td>
                                        <td class="py-3 pr-4 text-gray-900">
                                            {{ $vital->respiratory_rate ?? '—' }}
                                        </td>
                                        <td class="py-3 text-gray-500">
                                            {{ $vital->recordedBy?->name ?? 'Unknown' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <p>No vitals recorded</p>
                    </div>
                @endif
            </div>
        @else
            {{-- No Patient Selected --}}
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Patient Selected</h3>
                <p class="text-gray-500">Select a patient to view their vitals</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
