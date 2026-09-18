@php
    $patient = $this->getPatient();
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('Documents')"
        :description="__('Scanned files attached to the patient record and to each encounter.')"
        icon="heroicon-o-paper-clip"
        collapsible
        :collapsed="false"
    >
        @if ($patient)
            @livewire($this->relationManagerClass(), [
                'ownerRecord' => $patient,
                'pageClass' => static::class,
            ], key('patient-documents-'.$patient->getKey()))
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No patient selected.') }}</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
