<x-filament-widgets::widget>
    <x-filament::section>
        <div>
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                    <x-heroicon-m-clock class="w-4 h-4" />
                    Recent Patients
                </h4>
                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ $patients->count() }}
                </span>
            </div>

            @if($patients->isNotEmpty())
                <div class="space-y-2">
                    @foreach($patients as $patient)
                        <div class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                             wire:click="$dispatch('select-patient', { patientId: '{{ $patient->id }}' })">
                            <div class="w-10 h-10 rounded-full
                                        @if($patient->gender?->value === 'male') bg-info-100
                                        @elseif($patient->gender?->value === 'female') bg-warning-100
                                        @else bg-gray-100 @endif
                                        flex items-center justify-center">
                                <span class="text-sm font-semibold
                                             @if($patient->gender?->value === 'male') text-info-700
                                             @elseif($patient->gender?->value === 'female') text-warning-700
                                             @else text-gray-700 @endif">
                                    {{ substr($patient->full_name ?? 'U', 0, 2) }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $patient->full_name }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $patient->formatted_age ?? 'N/A' }} • {{ $patient->gender?->getLabel() ?? '' }}
                                </p>
                            </div>
                            @if($patient->latestEncounter?->isActive())
                                <div class="w-2 h-2 rounded-full
                                            {{ $patient->latestEncounter->type?->value === 'emergency' ? 'bg-danger-500' : 'bg-success-500' }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-4">No recent patients</p>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
