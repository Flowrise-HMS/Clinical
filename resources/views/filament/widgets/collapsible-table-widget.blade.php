@php
    $heading = $this->getTable()->getHeading();
    $description = $this->getTable()->getDescription();
@endphp

<x-filament-widgets::widget class="fi-wi-table">
    <x-filament::section
        :heading="$heading"
        :description="$description"
        collapsible
        :collapsed="true"
    >
        {{ $this->table ?? null }}
    </x-filament::section>
</x-filament-widgets::widget>
