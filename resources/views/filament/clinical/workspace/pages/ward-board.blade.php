<x-filament-panels::page class="bg-gray-50 dark:bg-gray-950">
    @php
        $ward = $board['ward'] ?? null;
        $stats = $board['stats'] ?? [];
        $beds = $board['beds'] ?? [];
        $incoming = $board['incoming'] ?? [];
        $canUpdate = $this->canUpdateEncounters();
        $statusStyles = [
            'available' => 'border-success-300 bg-white dark:border-success-800 dark:bg-gray-900',
            'occupied' => 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900',
            'reserved' => 'border-warning-300 bg-warning-50/60 dark:border-warning-800 dark:bg-warning-950/20',
            'cleaning' => 'border-info-300 bg-info-50/60 dark:border-info-800 dark:bg-info-950/20',
            'blocked' => 'border-gray-300 bg-gray-100 dark:border-gray-700 dark:bg-gray-800',
        ];
        $alertStyles = [
            'danger' => 'bg-danger-100 text-danger-700 dark:bg-danger-900/40 dark:text-danger-300',
            'warning' => 'bg-warning-100 text-warning-800 dark:bg-warning-900/40 dark:text-warning-300',
            'info' => 'bg-info-100 text-info-700 dark:bg-info-900/40 dark:text-info-300',
            'gray' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        ];
    @endphp

    <div wire:poll.60s.visible="refreshBoard" class="space-y-6">
        @if ($branchId === null)
            <div class="mx-auto max-w-2xl rounded-3xl p-20 text-center dark:bg-gray-900">
                <x-heroicon-o-building-office-2 class="mx-auto mb-6 h-24 w-24 text-gray-300" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No branch selected</h2>
                <p class="mt-3 text-gray-500">Pick a branch from the header to see its wards.</p>
            </div>
        @elseif ($wardOptions === [])
            <div class="mx-auto max-w-2xl rounded-3xl p-20 text-center dark:bg-gray-900">
                <x-heroicon-o-home-modern class="mx-auto mb-6 h-24 w-24 text-gray-300" />
                <h2 class="text-2xl font-medium text-gray-900 dark:text-white">No wards in this branch</h2>
                <p class="mt-3 text-gray-500">Create a room with beds under Core → Locations to get a ward board.</p>
            </div>
        @else
            {{-- Ward selector + header --}}
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-64">
                    <label for="ward-select" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ward</label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select id="ward-select" wire:model.live="wardId">
                            @foreach ($wardOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                @if ($ward)
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                        @if ($ward['specialty'])
                            <x-filament::badge color="gray">{{ $ward['specialty'] }}</x-filament::badge>
                        @endif
                        <x-filament::badge color="gray">{{ $ward['gender_policy_label'] }}</x-filament::badge>
                        @if ($ward['capacity'])
                            <x-filament::badge color="gray">Capacity {{ $ward['capacity'] }}</x-filament::badge>
                        @endif
                        <span class="flex items-center gap-1">
                            <x-heroicon-m-user class="h-4 w-4 text-gray-400" />
                            Nurse in charge: <span class="font-medium text-gray-900 dark:text-white">{{ $ward['nurse_in_charge']['name'] ?? 'not set' }}</span>
                        </span>
                        <span class="text-gray-400">· refreshes every minute</span>
                    </div>
                @endif
            </div>

            {{-- Stats --}}
            @if ($stats)
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-8">
                    @foreach ([
                        ['Occupancy', $stats['occupancy_pct'].'%', $stats['occupancy_pct'] >= 90 ? 'text-danger-600' : 'text-gray-900 dark:text-white'],
                        ['Occupied', $stats['occupied'].' / '.$stats['beds_total'], 'text-gray-900 dark:text-white'],
                        ['Free', $stats['available'], 'text-success-600'],
                        ['Reserved', $stats['reserved'], 'text-warning-600'],
                        ['Cleaning', $stats['cleaning'], 'text-info-600'],
                        ['Admitted today', $stats['admissions_today'], 'text-gray-900 dark:text-white'],
                        ['Discharged today', $stats['discharges_today'], 'text-gray-900 dark:text-white'],
                        ['Long stays', $stats['long_stay'], $stats['long_stay'] > 0 ? 'text-warning-600' : 'text-gray-900 dark:text-white'],
                    ] as [$label, $value, $classes])
                        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                            <div class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-1 text-xl font-semibold tabular-nums {{ $classes }}">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Incoming requests --}}
            @if ($incoming !== [])
                <div class="rounded-xl border border-warning-300 bg-warning-50/50 p-4 dark:border-warning-700/60 dark:bg-warning-900/10">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Waiting for a bed ({{ count($incoming) }})</h3>
                    <ul class="mt-2 divide-y divide-warning-200/70 dark:divide-warning-800/50">
                        @foreach ($incoming as $request)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2" wire:key="incoming-{{ $request['id'] }}">
                                <div class="text-sm">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $request['patient_name'] ?? 'Patient' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">
                                        · {{ $request['encounter_number'] }} · requested by {{ $request['requested_by'] ?? 'unknown' }} {{ $request['requested_label'] }}
                                        @if ($request['preferred_bed']) · prefers {{ $request['preferred_bed'] }} @endif
                                        @if ($request['expires_label']) · expires {{ $request['expires_label'] }} @endif
                                    </span>
                                    @if ($request['notes'])
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $request['notes'] }}</p>
                                    @endif
                                </div>
                                @if ($canUpdate)
                                    <div class="flex items-center gap-2">
                                        {{ ($this->acceptIncomingAction)(['requestId' => $request['id']]) }}
                                        {{ ($this->rejectIncomingAction)(['requestId' => $request['id']]) }}
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Bed grid --}}
            @if ($beds === [])
                <div class="rounded-xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700">This ward has no active beds.</div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($beds as $bed)
                        @php $encounter = $bed['encounter']; @endphp
                        <div
                            wire:key="bed-{{ $bed['id'] }}"
                            class="flex flex-col rounded-xl border-2 p-4 shadow-sm {{ $statusStyles[$bed['status']] ?? $statusStyles['occupied'] }}"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $bed['name'] }}</div>
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $bed['code'] }}
                                        @if ($bed['bed_class']) · {{ \Modules\Core\Enums\BedClass::tryFrom($bed['bed_class'])?->getLabel() ?? $bed['bed_class'] }} @endif
                                    </div>
                                </div>
                                <x-filament::badge :color="$bed['status_color']">{{ $bed['status_label'] }}</x-filament::badge>
                            </div>

                            @if ($encounter)
                                @php $patient = $encounter['patient']; @endphp
                                <div class="mt-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ \Modules\Clinical\Filament\Clusters\Workspace\Pages\ClinicalWorkspace::getUrl(['patientId' => $patient['id'] ?? null]) }}" class="truncate text-base font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                            {{ $patient['name'] ?? 'Patient' }}
                                        </a>
                                        @if ($encounter['on_pass'])
                                            <x-filament::badge color="warning" size="sm">On pass</x-filament::badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $patient['mrn'] ?? '' }}
                                        @if (isset($patient['age'])) · {{ $patient['age'] }} yrs @endif
                                        @if (! empty($patient['gender'])) · {{ ucfirst($patient['gender']) }} @endif
                                    </div>
                                    @if ($encounter['chief_complaint'])
                                        <p class="mt-1 line-clamp-2 text-xs text-gray-600 dark:text-gray-300">{{ $encounter['chief_complaint'] }}</p>
                                    @endif
                                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-[11px]">
                                        <dt class="text-gray-500 dark:text-gray-400">Admitted</dt>
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $encounter['admitted_label'] ?? '—' }}</dd>
                                        <dt class="text-gray-500 dark:text-gray-400">Length of stay</dt>
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $encounter['los_label'] ?? '—' }}</dd>
                                        <dt class="text-gray-500 dark:text-gray-400">Expected discharge</dt>
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $encounter['expected_discharge_label'] ?? 'not set' }}</dd>
                                        <dt class="text-gray-500 dark:text-gray-400">Attending</dt>
                                        <dd class="truncate text-gray-800 dark:text-gray-200">{{ $encounter['attending']['name'] ?? '—' }}</dd>
                                        <dt class="text-gray-500 dark:text-gray-400">Nurse</dt>
                                        <dd class="truncate text-gray-800 dark:text-gray-200">{{ $encounter['nurse']['name'] ?? '—' }}</dd>
                                    </dl>

                                    @if ($encounter['alerts'] !== [])
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            @foreach ($encounter['alerts'] as $alert)
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $alertStyles[$alert['severity']] ?? $alertStyles['gray'] }}" @if ($alert['detail']) title="{{ $alert['detail'] }}" @endif>
                                                    {{ $alert['label'] }}@if ($alert['detail']) · {{ $alert['detail'] }}@endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                                    <a class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ \Modules\Clinical\Filament\Clusters\Workspace\Pages\Timeline::getUrl(['patient' => $patient['id'] ?? null, 'view' => 'canvas']) }}">Timeline</a>
                                    @if ($this->pharmacyEnabled())
                                        <a class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400" href="{{ \Modules\Clinical\Filament\Clusters\Workspace\Pages\MedicationCanvas::getUrl(['patient' => $patient['id'] ?? null]) }}">Medications</a>
                                    @endif
                                    @if ($canUpdate)
                                        {{ ($this->assignNurseAction)(['encounterId' => $encounter['id']]) }}
                                        {{ ($this->setExpectedDischargeAction)(['encounterId' => $encounter['id']]) }}
                                        @if ($encounter['on_pass'])
                                            {{ ($this->returnFromPassAction)(['encounterId' => $encounter['id']]) }}
                                        @else
                                            {{ ($this->sendOnPassAction)(['encounterId' => $encounter['id']]) }}
                                            {{ ($this->transferAction)(['encounterId' => $encounter['id']]) }}
                                        @endif
                                    @endif
                                    @if ($this->canDischarge())
                                        {{ ($this->dischargeAction)(['encounterId' => $encounter['id']]) }}
                                    @endif
                                </div>
                            @else
                                <div class="mt-3 flex-1 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($bed['reserved_for'])
                                        Reserved for <span class="font-medium text-gray-800 dark:text-gray-200">{{ $bed['reserved_for']['patient_name'] ?? 'an admission' }}</span>
                                    @elseif ($bed['status_reason'])
                                        {{ $bed['status_reason'] }}
                                    @else
                                        Empty
                                    @endif
                                </div>
                                @if ($this->canManageBedStatus() && $bed['manual_transitions'] !== [])
                                    <div class="mt-auto border-t border-gray-100 pt-3 text-xs dark:border-gray-800">
                                        {{ ($this->setBedStatusAction)(['bedId' => $bed['id']]) }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
