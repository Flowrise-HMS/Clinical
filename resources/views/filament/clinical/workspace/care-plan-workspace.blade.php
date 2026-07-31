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

            @php($carePlans = $this->wardCarePlans())
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent care plans</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Draft, active, and on-hold plans for your branch.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Patient</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Encounter</th>
                                <th class="px-4 py-3">Custodian</th>
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($carePlans as $carePlan)
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="px-4 py-3">
                                        <button
                                            type="button"
                                            wire:click="selectPatient('{{ $carePlan->patient_id }}')"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ $carePlan->patient?->full_name ?? 'Unknown patient' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$carePlan->status->getColor()">
                                            {{ $carePlan->status->getLabel() }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">{{ $carePlan->category->getLabel() }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->encounter?->encounter_number ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->custodian?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->period_start?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($carePlan->status === \Modules\Clinical\Enums\CarePlanStatus::DRAFT || $carePlan->status === \Modules\Clinical\Enums\CarePlanStatus::ON_HOLD)
                                            <x-filament::button
                                                type="button"
                                                wire:click="resumeCarePlan('{{ $carePlan->id }}')"
                                                color="primary"
                                                size="sm"
                                            >
                                                Resume
                                            </x-filament::button>
                                        @else
                                            <x-filament::button type="button" wire:click="openPreview('{{ $carePlan->id }}')" color="gray" size="sm">
                                                Preview
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No recent care plans in this branch.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @else
        @php($recentCarePlans = $this->recentCarePlans())
        @php($previousCarePlans = $this->previousCarePlans())

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
                <x-filament::section>
                    <x-slot name="heading">Care plan authoring</x-slot>
                    <x-slot name="description">Complete each step in order before activating this draft.</x-slot>

                    <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">1. HEADER</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Plan details and allergies</p>
                            <x-filament::button type="button" wire:click="mountAction('editCarePlanHeader')" class="mt-3" color="gray" size="sm">
                                Edit header
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">2. ASSESSMENT</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Problems, strengths, and medical diagnoses</p>
                            <x-filament::button type="button" wire:click="mountAction('addCarePlanAssessment')" class="mt-3" color="gray" size="sm">
                                Add assessment
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="mountAction('attachMedicalDiagnosis')" class="mt-2" color="gray" size="sm">
                                Attach medical diagnosis
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">3. ROUTINE CARE</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Specify each required routine item</p>
                            <x-filament::button type="button" wire:click="mountAction('addRoutineCare')" class="mt-3" color="gray" size="sm">
                                Set routine care
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">4. NANDA + PES</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Diagnosis and at least three orders</p>
                            <x-filament::button type="button" wire:click="mountAction('addNursingDiagnosis')" class="mt-3" color="gray" size="sm">
                                Add nursing diagnosis
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">5. INTERVENTIONS</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Document completed nursing actions</p>
                            <x-filament::button type="button" wire:click="mountAction('recordIntervention')" class="mt-3" color="gray" size="sm">
                                Record intervention
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">6. EVALUATION</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">Evaluate expected outcomes</p>
                            <x-filament::button type="button" wire:click="mountAction('evaluateCarePlanObjective')" class="mt-3" color="gray" size="sm">
                                Evaluate objective
                            </x-filament::button>
                        </li>
                        <li class="rounded-lg border border-success-300 bg-success-50 p-3 dark:border-success-700 dark:bg-success-950/20">
                            <p class="text-xs font-semibold text-success-700 dark:text-success-300">READY TO ACTIVATE</p>
                            <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">The service validates all prerequisites.</p>
                            <x-filament::button type="button" wire:click="activateCarePlan('{{ $draftCarePlan->id }}')" class="mt-3" color="success" size="sm">
                                Activate plan
                            </x-filament::button>
                        </li>
                    </ol>

                    <div class="mt-6 grid gap-4 lg:grid-cols-2">
                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Diagnoses</p>
                            <div class="space-y-2">
                                @forelse ($draftCarePlan->medicalDiagnoses as $diagnosis)
                                    <p class="text-sm text-gray-700 dark:text-gray-200">
                                        <x-filament::badge color="info">Medical</x-filament::badge>
                                        {{ $diagnosis->description }}
                                    </p>
                                @empty
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No medical diagnosis attached.</p>
                                @endforelse
                                @foreach ($draftCarePlan->diagnoses as $diagnosis)
                                    <p class="text-sm text-gray-700 dark:text-gray-200">
                                        <x-filament::badge color="primary">Nursing</x-filament::badge>
                                        {{ $diagnosis->composed_statement }}
                                    </p>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">Activation checklist</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $draftCarePlan->problems->count() }} problem(s),
                                {{ $draftCarePlan->routineCares->count() }} routine care item(s), and
                                {{ $draftCarePlan->diagnoses->count() }} nursing diagnosis(es) recorded.
                            </p>
                        </div>
                    </div>
                </x-filament::section>
            @endif

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent care plans</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Encounter</th>
                                <th class="px-4 py-3">Custodian</th>
                                <th class="px-4 py-3">Updated</th>
                                <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($recentCarePlans as $carePlan)
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$carePlan->status->getColor()">
                                            {{ $carePlan->status->getLabel() }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3">{{ $carePlan->category->getLabel() }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->encounter?->encounter_number ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->custodian?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->updated_at?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($carePlan->status === \Modules\Clinical\Enums\CarePlanStatus::DRAFT || $carePlan->status === \Modules\Clinical\Enums\CarePlanStatus::ON_HOLD)
                                            <x-filament::button type="button" wire:click="resumeCarePlan('{{ $carePlan->id }}')" color="primary" size="sm">
                                                Resume
                                            </x-filament::button>
                                        @else
                                            <x-filament::button type="button" wire:click="selectCarePlan('{{ $carePlan->id }}')" color="gray" size="sm">
                                                Open
                                            </x-filament::button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No recent care plans.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Previous care plans</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/50 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Category</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Encounter</th>
                                <th class="px-4 py-3">Closed</th>
                                <th class="px-4 py-3"><span class="sr-only">Preview</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($previousCarePlans as $carePlan)
                                <tr class="text-gray-700 dark:text-gray-200">
                                    <td class="px-4 py-3">{{ $carePlan->category->getLabel() }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->status->getLabel() }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->encounter?->encounter_number ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $carePlan->completed_at?->format('M j, Y') ?? $carePlan->revoked_at?->format('M j, Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <x-filament::button type="button" wire:click="openPreview('{{ $carePlan->id }}')" color="gray" size="sm">
                                            Preview
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">No previous care plans.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
