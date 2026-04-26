<x-filament-panels::page>
    {{ $this->infolist() }}

    <div>
        @if($currentPatient)
            <x-filament::section heading="Clinical Notes">
                @if($clinicalNotes->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($clinicalNotes as $note)
                            <x-filament::section class="mt-5">
                                <x-slot name="heading">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center gap-3">
                                            @php
                                                $color = 'gray';

                                                if($note->note_type?->value === 'progress')
                                                    $color = 'primary';
                                                elseif($note->note_type?->value === 'discharge')
                                                    $color = 'success';
                                                elseif($note->note_type?->value === 'admission')
                                                    $color = 'warning';
                                            @endphp
                                            <x-filament::badge :color="$color">

                                                {{ $note->note_type?->getLabel() ?? 'General' }}

                                            </x-filament::badge>

                                            <x-filament::badge color="{{ $note->is_signed ? 'success' : 'gray' }}">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium">
                                                    {{ $note->is_signed ? 'Signed' : 'Draft' }}
                                                </span>
                                            </x-filament::badge>
                                        </div>
                                        <x-filament::badge>
                                            {{ $note->created_at?->format('M d, Y H:i') ?? '' }}
                                        </x-filament::badge>
                                    </div>
                                </x-slot>
                                <div class="prose prose-sm p-5 max-w-none">
                                    {!! nl2br(($note->content)) !!}
                                </div>

                                <x-slot name="footer">
                                    <div class="flex items-center justify-between mt-3 pt-3">
                                        <div class="text-sm">
                                            Author: {{ $note->author?->name ?? 'Unknown' }}
                                        </div>
                                        @if($note->is_signed && $note->signed_at)
                                            <div class="text-sm">
                                                Signed: {{ $note->signed_at?->format('M d, Y H:i') }}
                                            </div>
                                        @endif
                                    </div>
                                </x-slot>
                            </x-filament::section>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <p>No clinical notes found</p>
                    </div>
                @endif
            </x-filament::section>
        @else
            {{-- No Patient Selected --}}
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Patient Selected</h3>
                <p class="text-gray-500">Select a patient to view their notes</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
