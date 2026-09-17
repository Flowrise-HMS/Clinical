<x-filament-panels::page class="p-0 bg-gray-50 dark:bg-gray-950">
    <div wire:poll.30s.visible="refreshCanvas">
        @if($emptyReason === '')
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span>
                    In-facility medications for encounter
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $canvasMeta['encounterLabel'] ?? '' }}</span>.
                    Times shown in {{ $canvasMeta['timezone'] ?? config('app.timezone') }}; refreshes every 30 seconds.
                </span>
                <span>Fold a medication to hide its doses. Click the highlighted slot (or Record dose) to record the next dose.</span>
            </div>

            @include('clinical::clinical.workspace.partials.canvas', [
                'canvasKey' => \Modules\Clinical\Models\CanvasLayout::KEY_MEDICATIONS,
                'tree' => $canvasTree,
                'meta' => $canvasMeta,
                'layout' => $savedLayout,
                'readOnly' => false,
            ])
        @else
            @php
                $empty = match ($emptyReason) {
                    'no_patient' => ['icon' => 'heroicon-o-user-circle', 'title' => 'No Patient Selected', 'body' => 'Please select a patient from the workspace to view their medications.'],
                    'pharmacy_disabled' => ['icon' => 'heroicon-o-beaker', 'title' => 'Pharmacy module is not available', 'body' => 'Dose schedules come from the Pharmacy module. Enable it to use the medication canvas.'],
                    'no_active_encounter' => ['icon' => 'heroicon-o-clipboard-document-list', 'title' => 'No active encounter', 'body' => 'The medication canvas shows in-facility prescriptions for the patient\'s active encounter. Open an encounter to get started.'],
                    default => ['icon' => 'heroicon-o-beaker', 'title' => 'No in-facility medications', 'body' => 'No in-facility prescriptions have been ordered on the active encounter yet. Take-home prescriptions are not shown here.'],
                };
            @endphp

            <div class="mx-auto max-w-2xl rounded-3xl p-20 text-center dark:bg-gray-900">
                <x-dynamic-component :component="$empty['icon']" class="mx-auto mb-6 h-24 w-24 text-gray-300" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">{{ $empty['title'] }}</h2>
                <p class="mt-3 text-gray-500">{{ $empty['body'] }}</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
