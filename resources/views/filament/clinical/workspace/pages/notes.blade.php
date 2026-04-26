<x-filament-panels::page>
    {{ $this->infolist() }}

    {{-- Notes Content --}}
    <div class="p-6 bg-gray-50 min-h-[calc(100vh-8rem)]">
        @if($currentPatient)
            <div class="rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Clinical Notes</h3>

                @if($clinicalNotes->isNotEmpty())
                    <div class="space-y-4">
                        @foreach($clinicalNotes as $note)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-primary-200 transition-colors">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            @if($note->note_type?->value === 'progress') bg-primary-100 text-primary-700
                                            @elseif($note->note_type?->value === 'discharge') bg-success-100 text-success-700
                                            @elseif($note->note_type?->value === 'admission') bg-warning-100 text-warning-700
                                            @else bg-gray-100 text-gray-700
                                            @endif">
                                            {{ $note->note_type?->getLabel() ?? 'General' }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-xs font-medium
                                            {{ $note->is_signed ? 'bg-success-100 text-success-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $note->is_signed ? 'Signed' : 'Draft' }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        {{ $note->created_at?->format('M d, Y H:i') ?? '' }}
                                    </div>
                                </div>

                                <div class="prose prose-sm max-w-none text-gray-700">
                                    {!! nl2br(e($note->content)) !!}
                                </div>

                                <div class="flex items-center justify-between mt-3 pt-3 border-t">
                                    <div class="text-sm text-gray-500">
                                        Author: {{ $note->author?->name ?? 'Unknown' }}
                                    </div>
                                    @if($note->is_signed && $note->signed_at)
                                        <div class="text-sm text-gray-500">
                                            Signed: {{ $note->signed_at?->format('M d, Y H:i') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <p>No clinical notes found</p>
                    </div>
                @endif
            </div>
        @else
            {{-- No Patient Selected --}}
            <div class="flex flex-col items-center justify-center h-64 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Patient Selected</h3>
                <p class="text-gray-500">Select a patient to view their notes</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
