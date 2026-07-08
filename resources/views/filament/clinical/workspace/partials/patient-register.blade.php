<div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6 space-y-4 mt-4" wire:key="register-patient-form-{{ $registrationFormKey }}">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Register New Patient</h3>
        <x-filament::button wire:click="cancelRegistration" color="gray" size="sm" outlined>
            Cancel
        </x-filament::button>
    </div>

    @if (count($this->similarPatientsForRegistration) > 0 && ! $confirmDuplicateRegistration)
        <div class="rounded-lg border border-warning-300 dark:border-warning-700 bg-warning-50 dark:bg-warning-900/20 p-4 space-y-3">
            <p class="text-sm font-medium text-warning-800 dark:text-warning-200">
                Similar patients already exist. Select one or confirm creating a new record.
            </p>
            <div class="space-y-2">
                @foreach ($this->similarPatientsForRegistration as $similar)
                    <button type="button" wire:click="selectPatient('{{ $similar['id'] }}')"
                        class="w-full text-left flex items-center justify-between p-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 hover:border-primary-300 transition-colors">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $similar['full_name'] ?? ($similar['first_name'] . ' ' . $similar['last_name']) }}
                        </span>
                        <span class="text-xs text-gray-500 font-mono">{{ $similar['mrn'] ?? '' }}</span>
                    </button>
                @endforeach
            </div>
            <x-filament::button wire:click="confirmRegisterDespiteDuplicates" color="warning" size="sm">
                Create new patient anyway
            </x-filament::button>
        </div>
    @endif

    {{ $this->registerPatientForm }}

    <div class="flex justify-end pt-2">
        <x-filament::button wire:click="registerPatient" color="primary" icon="heroicon-m-user-plus">
            Register Patient
        </x-filament::button>
    </div>
</div>
