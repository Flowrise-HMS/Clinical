@php
    use Modules\Clinical\Filament\Clusters\Clinical\Resources\Encounters\Schemas\TriageForm;

    $openEncounter = $this->getOpenEncounter();
    $history = $this->triageHistory();
    $latest = $history->first();
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('Triage (SATS)') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Clinical signs first, then the Triage Early Warning Score (TEWS).') }}
            </p>
        </div>

        @if ($latest)
            <div class="rounded-lg border border-gray-200 p-3 text-sm dark:border-gray-700">
                <div class="flex items-center gap-2">
                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold {{ TriageForm::badgeClasses($latest->final_category) }}">
                        {{ $latest->final_category->getLabel() }}
                    </span>
                    <span class="font-medium text-gray-900 dark:text-white">TEWS {{ $latest->tews_score }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('Triaged :time', ['time' => $latest->triaged_at->diffForHumans()]) }}
                    @if ($latest->triager) &middot; {{ $latest->triager->name }} @endif
                    @if ($target = $latest->targetAt())
                        &middot;
                        <span @class(['font-semibold text-danger-600 dark:text-danger-400' => $latest->isOverdue()])>
                            {{ $latest->isOverdue() ? __('Overdue since :time', ['time' => $target->format('H:i')]) : __('See by :time', ['time' => $target->format('H:i')]) }}
                        </span>
                    @endif
                </p>
            </div>
        @endif
    </div>

    @if (! $openEncounter)
        <div class="rounded-lg border border-warning-300 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-200">
            {{ __('Start an encounter for this patient before triaging.') }}
        </div>
    @elseif ($openEncounter->isInpatient())
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-300">
            {{ __('Inpatients are not triaged. Use vitals and the ward board for early warning.') }}
        </div>
    @else
        {{ $this->triageForm }}

        <div class="flex justify-end pt-2">
            <x-filament::button wire:click="saveTriage" wire:target="saveTriage" color="primary" icon="heroicon-m-check">
                {{ $latest ? __('Save re-triage') : __('Save triage') }}
            </x-filament::button>
        </div>
    @endif

    @if ($history->count() > 1)
        <div>
            <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Triage history for this visit') }}</h4>
            <ul class="divide-y divide-gray-100 rounded-lg border border-gray-200 text-sm dark:divide-gray-700 dark:border-gray-700">
                @foreach ($history as $assessment)
                    <li class="flex flex-wrap items-center gap-2 px-3 py-2">
                        <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-semibold {{ TriageForm::badgeClasses($assessment->final_category) }}">
                            {{ $assessment->final_category->shortLabel() }}
                        </span>
                        <span class="text-gray-900 dark:text-white">TEWS {{ $assessment->tews_score }}</span>
                        @if ($assessment->override_category)
                            <x-filament::badge color="warning" size="sm">{{ __('Overridden') }}</x-filament::badge>
                        @endif
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ $assessment->triaged_at->format('d M H:i') }}
                            @if ($assessment->triager) &middot; {{ $assessment->triager->name }} @endif
                            @if ($assessment->disposition) &middot; {{ $assessment->disposition->getLabel() }} @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
