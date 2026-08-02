<x-filament-panels::page>
    @if ($currentPatient === null)
        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <x-filament::input.wrapper>
                    <x-slot name="prefix">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-5 w-5 text-gray-400" />
                    </x-slot>

                    <x-filament::input
                        type="search"
                        wire:model.live="searchTerm"
                        placeholder="Search patients by name, MRN, or phone..."
                    />
                </x-filament::input.wrapper>

                @if (mb_strlen($searchTerm) >= 2)
                    <div class="mt-3 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                        @forelse ($searchResults as $result)
                            <button
                                type="button"
                                wire:click="selectPatient('{{ $result['id'] }}')"
                                class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <span>
                                    <span class="block font-medium text-gray-950 dark:text-white">
                                        {{ $result['full_name'] ?? trim(($result['first_name'] ?? '').' '.($result['last_name'] ?? '')) }}
                                    </span>
                                    <span class="block text-sm text-gray-500 dark:text-gray-400">MRN: {{ $result['mrn'] ?? '—' }}</span>
                                </span>
                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5 text-gray-400" />
                            </button>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No patients found.</p>
                        @endforelse
                    </div>
                @endif
            </div>

            @livewire(\Modules\Clinical\Filament\Widgets\CarePlanWardTableWidget::class, key('cp-ward-plans'))
        </div>
    @else
        <div class="space-y-6">
            <div class="flex flex-col justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-gray-700 dark:bg-gray-800">
                <div>
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ $currentPatient->full_name }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">MRN: {{ $currentPatient->mrn }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @if ($this->getOpenEncounter())
                        <x-filament::badge color="success">Open encounter</x-filament::badge>
                    @else
                        <x-filament::badge color="warning">No open encounter</x-filament::badge>
                    @endif
                    <x-filament::button type="button" wire:click="clearPatient" color="gray" outlined>
                        Clear patient
                    </x-filament::button>
                </div>
            </div>

            @can('create', \Modules\Clinical\Models\CarePlan::class)
                <div class="flex flex-col justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center dark:border-gray-700 dark:bg-gray-800">
                    <div>
                        <h2 class="font-semibold text-gray-950 dark:text-white">New care plan</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->canCreateCarePlan() ? 'Create a nursing care plan draft for the open encounter.' : 'An open encounter is required before a care plan can be created.' }}
                        </p>
                    </div>
                    <x-filament::button type="button" wire:click="createCarePlan" :disabled="! $this->canCreateCarePlan()" icon="heroicon-m-plus">
                        Create care plan
                    </x-filament::button>
                </div>
            @endcan

            @if ($draftCarePlan = $this->selectedCarePlan())
                @php($readiness = $this->carePlanActivationReadiness() ?? ['can_activate' => false, 'is_ready' => false, 'medical_diagnoses' => [], 'items' => [], 'by_key' => []])
                @php($allergiesItem = $readiness['by_key']['allergies'] ?? null)
                @php($strengthsItem = $readiness['by_key']['problem_strengths'] ?? null)
                @php($routineItem = $readiness['by_key']['routine_care'] ?? null)
                @php($nursingItem = $readiness['by_key']['nursing_diagnoses'] ?? null)
                @php($ordersItem = $readiness['by_key']['orders'] ?? null)
                @php($medicalItem = $readiness['by_key']['medical_diagnosis'] ?? null)

                <div class="space-y-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Care plan authoring</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Open a step to review its summary, actions, and recorded data.
                        </p>
                    </div>

                    <x-filament::section
                        collapsible
                        :collapsed="false"
                        persist-collapsed
                        id="care-plan-header-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">
                            <span class="inline-flex flex-wrap items-center gap-2">
                                1. Header
                                <x-filament::badge :color="$draftCarePlan->status->getColor()" size="sm">
                                    {{ $draftCarePlan->status->getLabel() }}
                                </x-filament::badge>
                                @if ($readiness['is_ready'])
                                    <x-filament::badge color="success" size="sm">Ready</x-filament::badge>
                                @else
                                    <x-filament::badge color="warning" size="sm">Incomplete</x-filament::badge>
                                @endif
                            </span>
                        </x-slot>
                        <x-slot name="description">
                            {{ $allergiesItem['detail'] ?? 'Plan details and allergies' }}
                            @if ($medicalItem && ! $medicalItem['passed'])
                                · Medical diagnosis required
                            @endif
                        </x-slot>
                        <x-slot name="afterHeader">
                            <div class="flex flex-wrap items-center gap-2" x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('editCarePlanHeader')" color="gray" size="sm">
                                    Edit header
                                </x-filament::button>
                                @can('view', $draftCarePlan)
                                    <x-filament::button
                                        tag="a"
                                        :href="route('clinical.care-plans.pdf', $draftCarePlan)"
                                        target="_blank"
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-m-printer"
                                    >
                                        Print / PDF
                                    </x-filament::button>
                                @endcan
                                @if ($draftCarePlan->status->canActivate())
                                    <x-filament::button
                                        type="button"
                                        wire:click="activateCarePlan('{{ $draftCarePlan->id }}')"
                                        color="success"
                                        size="sm"
                                        :disabled="! $readiness['can_activate']"
                                    >
                                        Activate plan
                                    </x-filament::button>
                                @else
                                    <x-filament::badge color="success">Plan active</x-filament::badge>
                                @endif
                            </div>
                        </x-slot>

                        <div class="space-y-3">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    Medical diagnoses
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($readiness['medical_diagnoses'] as $diagnosis)
                                        <x-filament::badge color="info">
                                            {{ $diagnosis['description'] }}
                                        </x-filament::badge>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No medical diagnosis attached yet.</p>
                                    @endforelse
                                </div>
                            </div>
                            @if ($draftCarePlan->status->canActivate() && ! $readiness['is_ready'])
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Complete the required items in each step below to enable activation.
                                </p>
                            @elseif ($draftCarePlan->status->canActivate() && $ordersItem && ! $ordersItem['passed'])
                                <p class="text-xs text-warning-700 dark:text-warning-300">
                                    You can still activate with fewer than 3 orders per diagnosis.
                                </p>
                            @endif
                        </div>
                    </x-filament::section>

                    <x-filament::section
                        collapsible
                        collapsed
                        persist-collapsed
                        id="care-plan-assessment-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">2. Assessment</x-slot>
                        <x-slot name="description">
                            <span @class([
                                'text-danger-600 dark:text-danger-400' => $strengthsItem && ! $strengthsItem['passed'],
                            ])>
                                {{ $strengthsItem['detail'] ?? 'Problems, strengths, and medical diagnoses' }}
                            </span>
                        </x-slot>
                        <x-slot name="afterHeader">
                            <div class="flex flex-wrap items-center gap-2" x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('addCarePlanAssessment')" color="gray" size="sm">
                                    Add assessment
                                </x-filament::button>
                                <x-filament::button type="button" wire:click="mountAction('attachMedicalDiagnosis')" color="gray" size="sm">
                                    Attach medical diagnosis
                                </x-filament::button>
                            </div>
                        </x-slot>

                        @livewire(\Modules\Clinical\Filament\Widgets\CarePlanProblemsTableWidget::class, ['carePlanId' => $draftCarePlan->id], key('cp-problems-'.$draftCarePlan->id))
                    </x-filament::section>

                    <x-filament::section
                        collapsible
                        collapsed
                        persist-collapsed
                        id="care-plan-routine-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">3. Routine care</x-slot>
                        <x-slot name="description">
                            <span @class([
                                'text-danger-600 dark:text-danger-400' => $routineItem && ! $routineItem['passed'],
                            ])>
                                {{ $routineItem['detail'] ?? 'Complete the full checklist in one pass' }}
                            </span>
                        </x-slot>
                        <x-slot name="afterHeader">
                            <div x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('addRoutineCare')" color="gray" size="sm">
                                    Set routine care
                                </x-filament::button>
                            </div>
                        </x-slot>

                        @livewire(\Modules\Clinical\Filament\Widgets\CarePlanRoutineCareTableWidget::class, ['carePlanId' => $draftCarePlan->id], key('cp-routine-'.$draftCarePlan->id))
                    </x-filament::section>

                    <x-filament::section
                        collapsible
                        collapsed
                        persist-collapsed
                        id="care-plan-nanda-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">4. NANDA + PES</x-slot>
                        <x-slot name="description">
                            <span @class([
                                'text-danger-600 dark:text-danger-400' => $nursingItem && ! $nursingItem['passed'],
                            ])>
                                {{ $nursingItem['detail'] ?? 'Catalogue or free-text diagnosis; PES optional' }}
                            </span>
                        </x-slot>
                        <x-slot name="afterHeader">
                            <div x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('addNursingDiagnosis')" color="gray" size="sm">
                                    Add nursing diagnosis
                                </x-filament::button>
                            </div>
                        </x-slot>

                        @livewire(\Modules\Clinical\Filament\Widgets\CarePlanDiagnosesTableWidget::class, ['carePlanId' => $draftCarePlan->id], key('cp-diagnoses-'.$draftCarePlan->id))
                    </x-filament::section>

                    <x-filament::section
                        collapsible
                        collapsed
                        persist-collapsed
                        id="care-plan-interventions-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">5. Interventions</x-slot>
                        <x-slot name="description">
                            <span @class([
                                'text-warning-700 dark:text-warning-300' => $ordersItem && ! $ordersItem['passed'],
                            ])>
                                {{ $ordersItem['detail'] ?? 'Document completed nursing actions' }}
                            </span>
                        </x-slot>
                        <x-slot name="afterHeader">
                            <div x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('recordIntervention')" color="gray" size="sm">
                                    Record intervention
                                </x-filament::button>
                            </div>
                        </x-slot>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Record completed nursing actions against orders. Order counts are shown on nursing diagnoses in step 4.
                        </p>

                        @livewire(\Modules\Clinical\Filament\Widgets\CarePlanInterventionsTableWidget::class, ['carePlanId' => $draftCarePlan->id], key('cp-interventions-'.$draftCarePlan->id))
                    </x-filament::section>

                    <x-filament::section
                        collapsible
                        collapsed
                        persist-collapsed
                        id="care-plan-evaluation-{{ $draftCarePlan->id }}"
                    >
                        <x-slot name="heading">6. Evaluation</x-slot>
                        <x-slot name="description">Evaluate expected outcomes</x-slot>
                        <x-slot name="afterHeader">
                            <div x-on:click.stop>
                                <x-filament::button type="button" wire:click="mountAction('evaluateCarePlanObjective')" color="gray" size="sm">
                                    Evaluate objective
                                </x-filament::button>
                            </div>
                        </x-slot>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Evaluate objectives once interventions are underway.
                        </p>

                        @livewire(\Modules\Clinical\Filament\Widgets\CarePlanObjectivesTableWidget::class, ['carePlanId' => $draftCarePlan->id], key('cp-objectives-'.$draftCarePlan->id))
                    </x-filament::section>
                </div>
            @endif

            @livewire(\Modules\Clinical\Filament\Widgets\CarePlanRecentTableWidget::class, ['patientId' => $this->patientId], key('cp-recent-'.$this->patientId))
            @livewire(\Modules\Clinical\Filament\Widgets\CarePlanPreviousTableWidget::class, ['patientId' => $this->patientId], key('cp-previous-'.$this->patientId))
        </div>
    @endif
</x-filament-panels::page>
