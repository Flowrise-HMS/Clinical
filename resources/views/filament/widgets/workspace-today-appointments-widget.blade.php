<x-filament-widgets::widget>
    <x-filament::section heading="{{ __('Todays appointments') }} ({{ $appointments->count() }})" icon="heroicon-m-calendar-days">
        <div>
            @if($appointments->isEmpty())
                <div class="flex flex-col items-center justify-center py-6 text-center text-sm text-gray-600 dark:text-gray-400">
                    <p>{{ __('No appointments scheduled for today in your branch.') }}</p>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($appointments as $appointment)
                        @php
                            $viewUrl = $this->appointmentViewUrl($appointment);
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ $appointment->patient?->full_name ?? __('Unknown patient') }}
                                    @if($appointment->patient?->mrn)
                                        <span class="text-gray-500 dark:text-gray-400">· MRN {{ $appointment->patient->mrn }}</span>
                                    @endif
                                </p>
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ $appointment->start_at?->format('g:i A') }}
                                    –
                                    {{ $appointment->end_at?->format('g:i A') }}
                                    @if($appointment->location?->name)
                                        · {{ $appointment->location->name }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge>
                                    {{ $appointment->status->getLabel() }}
                                </x-filament::badge>
                                @if($viewUrl)
                                    <a href="{{ $viewUrl }}" class="fi-link text-xs font-medium text-primary-600 hover:text-primary-500">
                                        {{ __('View') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
