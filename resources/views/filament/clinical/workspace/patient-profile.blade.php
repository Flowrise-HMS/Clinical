<x-filament-panels::page>
    @if ($currentPatient)
        <div class="space-y-6">
            {{-- Header Section --}}
            {{ $this->patientInfoList($currentPatient) }}
        </div>
    @else
        <div class="flex flex-col items-center justify-center h-64 text-center">
            <h3 class="text-lg font-medium text-gray-900 mb-2">No Patient Selected</h3>
            <p class="text-gray-500">Select a patient to view their profile</p>
        </div>
    @endif
</x-filament-panels::page>
