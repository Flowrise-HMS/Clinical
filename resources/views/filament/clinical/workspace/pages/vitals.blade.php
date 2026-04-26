<x-filament-panels::page>
    {{-- Patient Context Header --}}
    {{ $this->infolist() }}

    {{-- Vitals Content --}}
    <div class="p-6 min-h-[calc(100vh-8rem)]">
        @if($currentPatient)
            {{-- Current Vitals Card --}}
            @if($latestVitals)
                <div class="mb-6">
                    <x-filament::section heading="Current Vitals">
                        {{ $this->vitalsInfolist() }}
                    </x-filament::section>
                </div>
            @endif

            {{-- Vitals History --}}
            <div>
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
