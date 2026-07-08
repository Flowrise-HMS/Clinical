<div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Patient Details</h3>
    <p class="text-sm text-gray-500 dark:text-gray-400">Update patient demographics, contact, identifiers, and emergency contacts.</p>
    {{ $this->patientDetailsForm }}
    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="savePatientDetails" color="primary" icon="heroicon-m-check">
            Save Patient Details
        </x-filament::button>
    </div>
</div>
