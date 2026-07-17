<x-filament-panels::page>
    <div>
        @if ($mode === 'home' || $mode === 'register')
            {{-- ===== HOME / REGISTER MODE ===== --}}
            <div class="space-y-4 sm:space-y-6">
                {{-- Patient Search --}}
                <div class="flex flex-col sm:flex-row gap-2 sm:items-start">
                    <div class="relative flex-1" x-data="{ open: @entangle('searchTerm').live }">
                    <x-filament::input.wrapper>
                        <x-slot name="prefix">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </x-slot>
                        <x-filament::input
                            type="search"
                            wire:model.live="searchTerm"
                            placeholder="Search patients by name, MRN, or phone..."
                            class="text-base py-3"
                        />
                    </x-filament::input.wrapper>

                    {{-- Search Results Dropdown --}}
                    @if (strlen($searchTerm) >= 2 && count($searchResults) > 0)
                        <div
                            class="absolute z-50 my-3 w-full bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 max-h-96 overflow-y-auto">
                            @foreach ($searchResults as $result)
                                <button wire:click="selectPatient('{{ $result['id'] }}')"
                                    class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 last:border-0 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm shrink-0">
                                            {{ strtoupper(substr($result['first_name'] ?? '', 0, 1)) }}{{ strtoupper(substr($result['last_name'] ?? '', 0, 1)) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-medium text-gray-900 dark:text-white truncate">
                                                {{ $result['full_name'] ?? $result['first_name'] . ' ' . $result['last_name'] }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400 truncate">
                                                MRN: {{ $result['mrn'] ?? 'N/A' }}
                                                @if (!empty($result['phone']))
                                                    &middot; {{ $result['phone'] }}
                                                @endif
                                            </div>
                                        </div>
                                        <x-filament::icon name="heroicon-m-chevron-right"
                                            class="w-5 h-5 text-gray-400 shrink-0" />
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    </div>
                </div>

                @if ($mode === 'register')
                    @include('clinical::clinical.workspace.partials.patient-register')
                @endif

                {{-- Recent Patients --}}
                @if (count($searchResults) === 0 && strlen($searchTerm) < 2)
                    @php
                        $recentPatients = app(
                            \Modules\Clinical\Classes\Services\ClinicalWorkspaceService::class,
                        )->getRecentPatients(5);
                    @endphp
                    @if ($recentPatients->isNotEmpty())
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2 px-1">Recent Patients</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                @foreach ($recentPatients as $recent)
                                    <button wire:click="selectPatient('{{ $recent->id }}')"
                                        class="flex flex-col items-center p-3 rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:border-primary-300 dark:hover:border-primary-600 hover:shadow-sm transition-all text-center">
                                        <div
                                            class="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-700 dark:text-primary-300 font-semibold text-sm mb-1.5">
                                            {{ strtoupper(substr($recent->first_name, 0, 1)) }}{{ strtoupper(substr($recent->last_name ?? '', 0, 1)) }}
                                        </div>
                                        <span
                                            class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate w-full">{{ $recent->full_name }}</span>
                                        <span class="text-xs text-gray-400 truncate w-full">{{ $recent->mrn }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                {{-- No results state --}}
                @if ($mode === 'home' && strlen($searchTerm) >= 2 && count($searchResults) === 0)
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        <x-filament::icon name="heroicon-o-user-group"
                            class="w-16 h-16 text-gray-300 dark:text-gray-600" />
                        <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400 mt-3">No patients found</h3>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mb-3">Try a different search term or register a new patient</p>
                        @if ($this->canCreatePatient())
                            <x-filament::button wire:click="startRegistration" color="primary" icon="heroicon-m-user-plus"
                                class="mt-4">
                                Register New Patient
                            </x-filament::button>
                        @endif
                    </div>
                @endif
            </div>
        @endif
        @if ($mode === 'patient' && $currentPatient)
            {{-- ===== PATIENT CONTEXT MODE ===== --}}
            @livewire(\Modules\Clinical\Filament\Widgets\PatientVitalsOverviewWidget::class,[
                'patientId' => $currentPatient->id,
            ])
            {{-- Patient Banner --}}
            <div
                class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mt-5 mb-5 overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 p-3 sm:p-4">
                    {{-- Patient Photo / Initials --}}
                    <div class="flex items-center gap-3 sm:gap-4 flex-1 min-w-0">
                        @if ($currentPatient->photo)
                            <img src="{{ $currentPatient->photo_url }}" alt="client photo"
                                class="w-8 h-8 rounded-full object-cover shrink-0 border-2 border-gray-200 dark:border-gray-600">
                        @else
                            <div
                                class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-lg shrink-0 border-2 border-primary-200 dark:border-primary-700">
                                {{ strtoupper(substr($currentPatient->first_name, 0, 1)) }}{{ strtoupper(substr($currentPatient->last_name ?? '', 0, 1)) }}
                            </div>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h2 class="text-base sm:text-lg font-bold text-gray-900 dark:text-white truncate">
                                    {{ $currentPatient->full_name }}
                                </h2>
                                <span
                                    class="text-xs text-gray-400 dark:text-gray-500 font-mono">#{{ $currentPatient->mrn }}</span>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                <span>{{ $currentPatient->age ?? '?' }} yrs</span>
                                <span class="text-gray-300 dark:text-gray-600">|</span>
                                <span>{{ $currentPatient->gender?->getLabel() ?? 'N/A' }}</span>
                                @if ($currentEncounter)
                                    @php $chip = $this->getEncounterStatusChip(); @endphp
                                    <span class="text-gray-300 dark:text-gray-600 hidden sm:inline">|</span>
                                    <span class="hidden sm:inline">{{ $chip['type'] ?? 'Encounter' }}</span>
                                    @if ($chip['ward'] || $chip['bed'])
                                        <x-filament::badge color="gray" class="text-xs">
                                            {{ collect([$chip['ward'], $chip['bed']])->filter()->implode(' · ') }}
                                        </x-filament::badge>
                                    @endif
                                    @if ($chip['los'])
                                        <x-filament::badge color="info" class="text-xs">
                                            LOS {{ $chip['los'] }}
                                        </x-filament::badge>
                                    @endif
                                    @if ($currentEncounter->coverage_type ?? null)
                                        <x-filament::badge :color="$currentEncounter->coverage_type->getColor() ?? 'gray'" class="text-xs">
                                            {{ $currentEncounter->coverage_type->getLabel() }}
                                        </x-filament::badge>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Allergy Red Flag --}}
                        @if ($currentPatient->allergies?->isNotEmpty())
                            <x-filament::badge color="danger" icon="heroicon-m-exclamation-triangle" class="shrink-0">
                                {{ $currentPatient->allergies?->count() }}
                                Allerg{{ $currentPatient->allergies?->count() > 1 ? 'ies' : 'y' }}
                            </x-filament::badge>
                        @endif
                    </div>

                    {{-- Clear / Edit Buttons --}}
                    <div class="flex items-end gap-2 shrink-0">
                        @if ($currentEncounter)
                            <x-filament::badge :color="$currentEncounter->status?->getColor() ?? 'gray'" class="text-xs">
                                {{ $currentEncounter->status?->getLabel() ?? 'N/A' }}
                            </x-filament::badge>
                        @endif
                        @if ($this->canUpdateCurrentPatient())
                            <x-filament::button wire:click="$set('activeTab', 'patient-details')" color="gray"
                                size="sm" outlined icon="heroicon-m-pencil-square">
                                <span class="hidden sm:inline">Edit</span>
                            </x-filament::button>
                        @endif
                        <x-filament::button wire:click="clearPatient" color="info" size="sm" outlined
                            icon="heroicon-m-x-mark">
                            <span class="hidden sm:inline">Clear</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>

            @include('clinical::clinical.workspace.partials.post-registration-banner')

            {{-- Role-Specific Content --}}
            @php $role = $this->getUserRoleKey(); @endphp

            @if ($role === 'nurse')
                {{-- ===== NURSE VIEW ===== --}}
                <div class="space-y-4">
                    {{-- Tab Bar --}}
                    <div class="overflow-x-auto -mx-1 px-1 pb-1">
                        <div class="flex gap-1 min-w-max p-1 bg-gray-100/80 dark:bg-gray-900/60 border border-gray-200/50 dark:border-gray-800/50 rounded-xl backdrop-blur-sm shadow-inner">
                            @foreach ($this->getNurseTabs() as $tabKey => $tab)
                                <button wire:click="$set('activeTab', '{{ $tabKey }}')"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap
                                        {{ $activeTab === $tabKey
                                            ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/40 dark:border-gray-700/40 font-semibold transform scale-[1.02]'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-white/40 dark:hover:bg-gray-800/40' }}">
                                    @isset($tab['icon'])
                                        <x-filament::icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                                    @endisset
                                    <span>{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tab Content --}}
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                        @if ($activeTab === 'patient-details')
                            @include('clinical::clinical.workspace.partials.patient-details-tab')
                        @elseif ($activeTab === 'encounter')
                            @include('clinical::clinical.workspace.partials.encounter-tab')
                        @elseif ($activeTab === 'adt')
                            @include('clinical::clinical.workspace.partials.adt-tab')
                        @elseif ($activeTab === 'vitals')
                            <div class="space-y-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Record Vitals</h3>
                                {{ $this->vitalsForm }}
                                <div class="flex justify-end pt-2">
                                    <x-filament::button wire:click="saveVitals" color="primary" icon="heroicon-m-check">
                                        Save Vitals
                                    </x-filament::button>
                                </div>
                            </div>
                        @elseif($activeTab === 'allergies')
                            <div class="space-y-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Allergies</h3>
                                @if ($currentPatient->allergies?->isNotEmpty())
                                    <div class="space-y-2 mb-4">
                                        @foreach ($currentPatient->allergies as $allergy)
                                            <div
                                                class="flex items-center gap-2 p-2 rounded-lg bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-300 text-sm">
                                                <x-filament::icon name="heroicon-m-exclamation-triangle"
                                                    class="w-4 h-4 shrink-0" />
                                                <span class="font-medium">{{ $allergy->allergen }}</span>
                                                @if ($allergy->severity)
                                                    <x-filament::badge color="danger"
                                                        class="text-xs">{{ $allergy->severity?->getLabel() }}</x-filament::badge>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">No allergies recorded.</p>
                                @endif
                                {{ $this->allergyForm }}
                                <div class="flex justify-end pt-2">
                                    <x-filament::button wire:click="saveAllergy" color="primary" icon="heroicon-m-check">
                                        Save Allergy
                                    </x-filament::button>
                                </div>
                            </div>
                        @elseif($activeTab === 'triage')
                            <div class="space-y-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Triage</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Triage assessment and notes.</p>
                                <div>
                                    <div>
                                        {{ $this->triageForm }}
                                    </div>
                                </div>
                                <div class="flex justify-end pt-2">
                                    <x-filament::button wire:click="saveConsultation" color="primary"
                                        icon="heroicon-m-check">
                                        Save Triage Notes
                                    </x-filament::button>
                                </div>
                            </div>
                        @elseif($activeTab === 'history')
                            @include('clinical::clinical.workspace.history-tab')
                        @endif
                    </div>
                </div>
            @elseif($role === 'lab')
                {{-- ===== LAB TECHNICIAN VIEW ===== --}}
                <div class="space-y-4">
                    {{-- Tab Bar --}}
                    <div class="overflow-x-auto -mx-1 px-1 pb-1">
                        <div class="flex gap-1 min-w-max p-1 bg-gray-100/80 dark:bg-gray-900/60 border border-gray-200/50 dark:border-gray-800/50 rounded-xl backdrop-blur-sm shadow-inner">
                            @foreach ($this->getLabTabs() as $tabKey => $tab)
                                <button wire:click="$set('activeTab', '{{ $tabKey }}')"
                                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap
                                        {{ $activeTab === $tabKey
                                            ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/40 dark:border-gray-700/40 font-semibold transform scale-[1.02]'
                                            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-white/40 dark:hover:bg-gray-800/40' }}">
                                    @isset($tab['icon'])
                                        <x-filament::icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                                    @endisset
                                    <span>{{ $tab['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Tab Content --}}
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                        @if ($activeTab === 'patient-details')
                            @include('clinical::clinical.workspace.partials.patient-details-tab')
                        @elseif ($activeTab === 'pending-labs')
                            <div class="space-y-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Lab Requests</h3>
                                @php $pendingItems = $this->pendingLabItems; @endphp
                                @forelse($pendingItems as $item)
                                    <div
                                        class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600">
                                        <div>
                                            <p class="font-medium text-sm">
                                                {{ $item['service']['name'] ?? 'Unknown Service' }}</p>
                                            <p class="text-xs text-gray-500">Ordered {{ $item['created_at'] ?? '' }}</p>
                                        </div>
                                        <x-filament::badge :color="$item['status'] === 'in_progress' ? 'warning' : 'info'">
                                            {{ $item['status'] ?? 'pending' }}
                                        </x-filament::badge>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">No pending lab
                                        requests.</p>
                                @endforelse
                            </div>
                        @elseif($activeTab === 'submit-results')
                            <div class="space-y-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Submit Lab Results</h3>
                                <x-filament::input.wrapper label="Service">
                                    <x-filament::input.select wire:model.live="serviceRequestData.request_item_id">
                                        <option value="">Select pending lab...</option>
                                        @foreach ($this->pendingLabItems as $item)
                                            <option value="{{ $item['id'] }}">
                                                {{ $item['service']['name'] ?? 'Unknown' }}</option>
                                        @endforeach
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <div>
                                    {{ $this->labResultForm }}
                                </div>
                                <div class="flex justify-end pt-2">
                                    <x-filament::button wire:click="saveLabResult" color="primary"
                                        icon="heroicon-m-check">
                                        Submit Result
                                    </x-filament::button>
                                </div>
                            </div>
                        @elseif($activeTab === 'completed')
                            <div class="space-y-3">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Completed Results</h3>
                                @php $completedItems = $this->completedLabItems; @endphp
                                @forelse($completedItems as $item)
                                    <div
                                        class="flex items-center justify-between p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                                        <div>
                                            <p class="font-medium text-sm">{{ $item['service']['name'] ?? 'Unknown' }}</p>
                                            <p class="text-xs text-gray-500">Completed</p>
                                        </div>
                                        <x-filament::badge color="success">Completed</x-filament::badge>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400 py-8 text-center">No completed
                                        results.</p>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- ===== CLINICIAN VIEW (default) ===== --}}
                <div class="space-y-4">
                    {{-- Consultation Section --}}
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Consultation</h3>
                            @if (!$currentEncounter)
                                <x-filament::badge color="warning">No active encounter</x-filament::badge>
                            @endif
                        </div>
                        <div class="space-y-3">
                            <x-filament::input.wrapper label="Chief Complaint">
                                <x-filament::input type="text" wire:model="consultationChiefComplaint"
                                    placeholder="Primary reason for visit" />
                            </x-filament::input.wrapper>
                            <div>
                                {{ $this->consultationForm }}
                            </div>
                            <div class="flex justify-end">
                                <x-filament::button wire:click="saveConsultation" color="primary"
                                    icon="heroicon-m-document-text" :disabled="!$currentEncounter">
                                    Save Consultation
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    {{-- Diagnosis Section --}}
                    <div
                        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Diagnosis</h3>
                        <div class="space-y-3">
                            {{ $this->diagnosisForm }}

                            @if ($currentEncounter && count($diagnosisCodes) > 0)
                                <div class="flex justify-end pt-2">
                                    <x-filament::button wire:click="saveDiagnoses" color="primary"
                                        icon="heroicon-m-document-check">
                                        Save Diagnoses
                                    </x-filament::button>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Action Tabs --}}
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
                        {{-- Tab Bar --}}
                        <div class="overflow-x-auto -mx-1 px-1 pt-1 pb-1">
                            <div class="flex gap-1 min-w-max p-1 bg-gray-100/80 dark:bg-gray-900/60 border border-gray-200/50 dark:border-gray-800/50 rounded-t-xl mx-1 backdrop-blur-sm shadow-inner">
                                @foreach ($this->getClinicianTabs() as $tabKey => $tab)
                                    <button wire:click="$set('activeTab', '{{ $tabKey }}')"
                                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 whitespace-nowrap
                                            {{ $activeTab === $tabKey
                                                ? 'bg-white dark:bg-gray-800 text-primary-600 dark:text-primary-400 shadow-sm border border-gray-200/40 dark:border-gray-700/40 font-semibold transform scale-[1.02]'
                                                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-white/40 dark:hover:bg-gray-800/40' }}">
                                        @isset($tab['icon'])
                                            <x-filament::icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                                        @endisset
                                        <span>{{ $tab['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Tab Panel --}}
                        <div class="p-4 sm:p-6 border-t border-gray-200 dark:border-gray-700">
                            @if ($activeTab === 'patient-details')
                                @include('clinical::clinical.workspace.partials.patient-details-tab')
                            @elseif ($activeTab === 'encounter')
                                @include('clinical::clinical.workspace.partials.encounter-tab')
                            @elseif ($activeTab === 'adt')
                                @include('clinical::clinical.workspace.partials.adt-tab')
                            @elseif ($activeTab === 'vitals')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Record Vitals</h4>
                                    {{ $this->vitalsForm }}
                                    <div class="flex justify-end pt-2">
                                        <x-filament::button wire:click="saveVitals" color="primary"
                                            icon="heroicon-m-check">
                                            Save Vitals
                                        </x-filament::button>
                                    </div>
                                </div>
                            @elseif($activeTab === 'service-lab')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Service / Lab Orders
                                    </h4>
                                    {{ $this->serviceRequestForm }}
                                    <div class="flex justify-end pt-2">
                                        <x-filament::button wire:click="saveServiceRequest" color="primary"
                                            icon="heroicon-m-check">
                                            Send Orders
                                        </x-filament::button>
                                    </div>
                                </div>
                            @elseif($activeTab === 'medication')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Medication Orders
                                    </h4>
                                    {{ $this->medicationForm }}
                                    <div class="flex justify-end pt-2">
                                        <x-filament::button wire:click="saveMedicationOrder" color="primary"
                                            icon="heroicon-m-beaker">
                                            Send Prescription
                                        </x-filament::button>
                                    </div>
                                </div>
                            @elseif($activeTab === 'allergies')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Allergies</h4>
                                    @if ($currentPatient->allergies?->isNotEmpty())
                                        <div class="space-y-2 mb-4">
                                            @foreach ($currentPatient->allergies as $allergy)
                                                <div
                                                    class="flex items-center gap-2 p-2 rounded-lg bg-danger-50 dark:bg-danger-900/20 text-danger-700 dark:text-danger-300 text-sm">
                                                    <x-filament::icon name="heroicon-m-exclamation-triangle"
                                                        class="w-4 h-4 shrink-0" />
                                                    <span class="font-medium">{{ $allergy->allergen }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">No allergies recorded.</p>
                                    @endif
                                    @if ($this->allergyForm)
                                    {{ $this->allergyForm }}
                                    <div class="flex justify-end pt-2">
                                        <x-filament::button wire:click="saveAllergy" color="primary"
                                            icon="heroicon-m-check">
                                            Save Allergy
                                        </x-filament::button>
                                    </div>
                                    @endif
                                </div>
                            @elseif($activeTab === 'discharge')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Discharge Patient
                                    </h4>
                                    @if(!$this->hasOpenEncounter())
                                        <x-filament::badge color="warning">No active encounter to discharge</x-filament::badge>
                                    @else
                                        {{ $this->dischargeForm }}
                                        <div class="flex justify-end pt-2">
                                            <x-filament::button wire:click="saveDischarge" color="danger"
                                                icon="heroicon-m-arrow-right-on-rectangle">
                                                Complete Encounter
                                            </x-filament::button>
                                        </div>
                                    @endif
                                </div>
                            @elseif($activeTab === 'referral')
                                <div class="space-y-4">
                                    <h4 class="text-base font-semibold text-gray-900 dark:text-white">Referral</h4>
                                    {{ $this->referralForm }}
                                    <div class="flex justify-end pt-2">
                                        <x-filament::button wire:click="saveReferral" color="warning"
                                            icon="heroicon-m-arrow-path">
                                            Submit Referral
                                        </x-filament::button>
                                    </div>
                                </div>
                            @elseif($activeTab === 'history')
                                @include('clinical::clinical.workspace.history-tab')
                            @else
                                <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-8">
                                    Select a tab to get started.
                                </p>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endif
        @if ($mode === 'patient' && !$currentPatient)
            {{-- Fallback --}}
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <x-filament::icon name="heroicon-o-user" class="w-16 h-16 text-gray-300 dark:text-gray-600 mb-4" />
                <h3 class="text-lg font-medium text-gray-500 dark:text-gray-400">Patient not found</h3>
                <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">The selected patient could not be loaded.</p>
                <x-filament::button wire:click="clearPatient" color="info" class="mt-4">
                    Back to Workspace
                </x-filament::button>
            </div>
        @endif

    </div>
</x-filament-panels::page>
