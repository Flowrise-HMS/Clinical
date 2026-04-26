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
                    {{ $this->vitalsInfolist() }}
                </div>
            @endif

            {{-- Vitals History --}}
            <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
                <div>{{ $this->table }}</div>
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
