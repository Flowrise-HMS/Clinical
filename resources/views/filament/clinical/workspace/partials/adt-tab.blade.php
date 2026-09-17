@php
    $openEncounter = $this->getOpenEncounter();
    $chip = $this->getEncounterStatusChip();
    $canCreate = $this->canCreateEncounter();
    $canUpdate = $openEncounter ? $this->canUpdateEncounter($openEncounter) : false;
    $canShowAdmit = $openEncounter ? $this->canShowAdmitOnAdt($openEncounter) : false;
    $canShowDischarge = $openEncounter ? $this->canShowDischargeOnAdt($openEncounter) : false;
    $canDischarge = $openEncounter ? $this->canDischargeEncounter($openEncounter) : false;
    $canShowComplete = $openEncounter ? $this->canShowCompleteOnAdt($openEncounter) : false;
    $canDecideAdmission = $openEncounter ? $this->canShowAdmissionDecisionOnAdt($openEncounter) : false;
    $pendingAdmission = $openEncounter ? $this->getPendingAdmissionRequest() : null;
    $decidedAdmission = $openEncounter ? $this->getLatestDecidedAdmissionRequest() : null;
    $hasBed = $openEncounter && filled($openEncounter->bed_id);
    $renderedSection = false;
@endphp
<div class="space-y-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Admit / Transfer / Discharge</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage bed assignment and patient movement for this visit.
            </p>
        </div>
        @if ($openEncounter)
            <div class="flex flex-wrap items-center gap-2">
                @if ($chip['type'])
                    <x-filament::badge color="primary">{{ $chip['type'] }}</x-filament::badge>
                @endif
                @if ($chip['status'])
                    <x-filament::badge :color="$chip['status_color']">{{ $chip['status'] }}</x-filament::badge>
                @endif
                @if ($chip['ward'] || $chip['bed'])
                    <x-filament::badge color="gray">
                        {{ collect([$chip['ward'], $chip['bed']])->filter()->implode(' · ') }}
                    </x-filament::badge>
                @endif
                @if ($chip['los'])
                    <x-filament::badge color="info">Admitted for {{ $chip['los'] }}</x-filament::badge>
                @endif
                @if ($chip['admission_pending'] ?? false)
                    <x-filament::badge color="warning" icon="heroicon-m-clock">Admission pending</x-filament::badge>
                @endif
            </div>
        @endif
    </div>

    @if (! $openEncounter)
        @if ($canCreate)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Admit Patient</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Create a new inpatient encounter to admit the patient to a ward.
                </p>
                {{ $this->adtTransferInForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="transferIn" color="success" icon="heroicon-m-arrow-left-end-on-rectangle">
                        Admit Patient
                    </x-filament::button>
                </div>
            </div>
        @endif
    @else
        @if ($pendingAdmission)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-warning-300 dark:border-warning-700/60 bg-warning-50/50 dark:bg-warning-900/10 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Admission awaiting ward acceptance</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            Requested to <span class="font-medium">{{ $pendingAdmission->requestedWard?->name ?? 'ward' }}</span>
                            @if ($pendingAdmission->requestedBed)
                                (preferred bed {{ $pendingAdmission->requestedBed->name }})
                            @endif
                            by {{ $pendingAdmission->requester?->name ?? 'unknown' }}
                            {{ $pendingAdmission->requested_at?->diffForHumans() }}.
                        </p>
                        @if (filled($pendingAdmission->notes))
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $pendingAdmission->notes }}</p>
                        @endif
                    </div>
                    @if ($canDecideAdmission)
                        <div class="flex items-center gap-2">
                            {{ $this->acceptAdmissionAction }}
                            {{ $this->rejectAdmissionAction }}
                        </div>
                    @endif
                </div>
                @unless ($canDecideAdmission)
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Ward staff will accept the request and confirm the bed, or reject it with a reason.
                    </p>
                @endunless
            </div>
        @endif

        @if ($decidedAdmission && $decidedAdmission->status === \Modules\Clinical\Enums\AdmissionRequestStatus::Rejected && ! $hasBed)
            @php $renderedSection = true; @endphp
            <div class="space-y-1 rounded-xl border border-danger-300 dark:border-danger-700/60 bg-danger-50/50 dark:bg-danger-900/10 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Last admission request was rejected</h4>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $decidedAdmission->requestedWard?->name ?? 'Ward' }} declined
                    {{ $decidedAdmission->decided_at?->diffForHumans() }}
                    @if ($decidedAdmission->decider)
                        by {{ $decidedAdmission->decider->name }}
                    @endif
                    @if (filled($decidedAdmission->decision_notes))
                        &mdash; {{ $decidedAdmission->decision_notes }}
                    @endif
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">You can request admission to another ward below, or complete the visit.</p>
            </div>
        @endif

        @if ($canShowAdmit)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Request admission</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Choose the ward (and optionally a preferred bed). Ward staff accept the request and confirm the bed, or reject it with a reason.
                </p>
                {{ $this->adtAdmitForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="admitToBed" color="success" icon="heroicon-m-arrow-right-start-on-rectangle">
                        Request admission
                    </x-filament::button>
                </div>
            </div>
        @elseif ($canUpdate && ! $hasBed && ! $pendingAdmission)
            @php $renderedSection = true; @endphp
            <x-filament::badge color="info">
                Admission is not available for this encounter in its current status.
            </x-filament::badge>
        @endif

        @if ($canUpdate && $hasBed)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Internal transfer</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Move the patient to another ward or bed. The encounter continues.
                </p>
                {{ $this->adtTransferInternalForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="transferInternal" color="info" icon="heroicon-m-arrows-right-left">
                        Transfer internally
                    </x-filament::button>
                </div>
            </div>
        @endif

        @if ($canDischarge && $hasBed)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Transfer out</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    End this encounter and send the patient to another branch or external facility.
                </p>
                {{ $this->adtTransferOutForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="transferOut" color="warning" icon="heroicon-m-building-office-2">
                        Transfer out
                    </x-filament::button>
                </div>
            </div>
        @endif

        @if ($canShowComplete)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-success-200 dark:border-success-900/40 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Complete visit</h4>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Close this encounter once the consultation is over. Pending charges are finalized for billing.
                        </p>
                    </div>
                    {{ $this->completeEncounterAction }}
                </div>
            </div>
        @endif

        @if ($canShowDischarge)
            @php $renderedSection = true; @endphp
            <div class="space-y-3 rounded-xl border border-danger-200 dark:border-danger-900/40 p-4">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Discharge</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Complete the encounter, free the bed, and trigger billing finalization.
                </p>
                {{ $this->dischargeForm }}
                <div class="flex justify-end">
                    <x-filament::button wire:click="saveDischarge" color="danger" icon="heroicon-m-arrow-right-on-rectangle">
                        Discharge patient
                    </x-filament::button>
                </div>
            </div>
        @endif
    @endif

    @unless ($renderedSection)
        <x-filament::badge color="warning">
            You do not have permission to perform ADT actions for this patient.
        </x-filament::badge>
    @endunless
</div>
