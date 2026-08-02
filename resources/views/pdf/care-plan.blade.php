<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Nursing Care Plan') }} — {{ $carePlan->patient?->full_name ?? __('Patient') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 12px 0 4px; }
        .muted { color: #6b7280; }
        .section { margin-top: 4px; }
        .meta-grid { width: 100%; margin-top: 8px; }
        .meta-grid td { vertical-align: top; padding: 2px 8px 2px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #f3f4f6; font-weight: 700; font-size: 10px; }
        .center { text-align: center; }
        .diagnosis-block { margin-top: 10px; padding: 8px; border: 1px solid #e5e7eb; page-break-inside: avoid; }
        .badge { display: inline-block; padding: 1px 6px; border: 1px solid #9ca3af; border-radius: 3px; font-size: 10px; }
        ul.plain { margin: 4px 0; padding-left: 16px; }
        .footer { margin-top: 24px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    @include('core::print.partials.pdf-brand-header', [
        'branchId' => $carePlan->branch_id,
        'subtitle' => $carePlan->branch?->name,
    ])

    <h1>{{ __('Nursing Care Plan') }}</h1>
    <p class="muted">
        {{ $carePlan->category?->getLabel() ?? $carePlan->category }}
        · {{ $carePlan->status?->getLabel() ?? $carePlan->status }}
        @if ($carePlan->title)
            · {{ $carePlan->title }}
        @endif
    </p>

    <h2>{{ __('Patient & plan') }}</h2>
    <table class="meta-grid">
        <tr>
            <td style="width: 50%;">
                <strong>{{ __('Patient') }}:</strong> {{ $carePlan->patient?->full_name ?? '—' }}<br>
                <strong>{{ __('MRN') }}:</strong> {{ $carePlan->patient?->mrn ?? '—' }}<br>
                <strong>{{ __('Encounter') }}:</strong> {{ $carePlan->encounter?->encounter_number ?? $carePlan->encounter_id ?? '—' }}
            </td>
            <td style="width: 50%;">
                <strong>{{ __('Author') }}:</strong> {{ $carePlan->author?->name ?? '—' }}<br>
                <strong>{{ __('Custodian') }}:</strong> {{ $carePlan->custodian?->name ?? '—' }}<br>
                <strong>{{ __('Period') }}:</strong>
                {{ $carePlan->period_start?->format('Y-m-d') ?? '—' }}
                →
                {{ $carePlan->period_end?->format('Y-m-d') ?? '—' }}
            </td>
        </tr>
    </table>

    @if ($carePlan->description)
        <p class="section"><strong>{{ __('Description') }}:</strong> {{ $carePlan->description }}</p>
    @endif

    <p class="section">
        <strong>{{ __('Allergies') }}:</strong>
        @if ($carePlan->no_known_allergies)
            {{ __('No known allergies') }}
        @else
            {{ __('See patient allergy record') }}
        @endif
    </p>

    <h2>{{ __('Medical diagnoses') }}</h2>
    @forelse ($carePlan->medicalDiagnoses as $medicalDiagnosis)
        <div class="badge" style="margin: 2px 4px 2px 0;">{{ $medicalDiagnosis->description }}</div>
    @empty
        <p class="muted">{{ __('None attached.') }}</p>
    @endforelse

    <h2>{{ __('Problems & strengths') }}</h2>
    @forelse ($carePlan->problems as $problem)
        <div class="section" style="margin-bottom: 8px;">
            <strong>{{ $problem->label }}</strong>
            <span class="muted">({{ $problem->status?->getLabel() ?? $problem->status }})</span>
            @if ($problem->description)
                <div>{{ $problem->description }}</div>
            @endif
            @if ($problem->strengths->isNotEmpty())
                <ul class="plain">
                    @foreach ($problem->strengths as $strength)
                        <li>{{ $strength->description ?: '—' }}</li>
                    @endforeach
                </ul>
            @else
                <p class="muted">{{ __('No strengths recorded.') }}</p>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('No problems identified.') }}</p>
    @endforelse

    <h2>{{ __('Routine care') }}</h2>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Specification') }}</th>
                <th>{{ __('N/A') }}</th>
                <th>{{ __('Notes') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($carePlan->routineCares as $routine)
                <tr>
                    <td>{{ $routine->item?->getLabel() ?? $routine->item }}</td>
                    <td>{{ $routine->specification ?: '—' }}</td>
                    <td class="center">{{ $routine->not_applicable ? __('Yes') : '—' }}</td>
                    <td>{{ $routine->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted">{{ __('No routine care recorded.') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('Nursing diagnoses, orders & interventions') }}</h2>
    @forelse ($carePlan->diagnoses as $diagnosis)
        <div class="diagnosis-block">
            <h3>{{ $diagnosis->displayLabel() }}</h3>
            @if (filled($diagnosis->composed_statement))
                <p>{{ $diagnosis->composed_statement }}</p>
            @else
                @if (filled($diagnosis->problem_statement) || filled($diagnosis->related_to) || filled($diagnosis->as_evidenced_by))
                    <p>
                        @if (filled($diagnosis->problem_statement))
                            <strong>{{ __('Problem') }}:</strong> {{ $diagnosis->problem_statement }}
                        @endif
                        @if (filled($diagnosis->related_to))
                            <br><strong>{{ __('Related to') }}:</strong> {{ $diagnosis->related_to }}
                        @endif
                        @if (filled($diagnosis->as_evidenced_by))
                            <br><strong>{{ __('As evidenced by') }}:</strong> {{ $diagnosis->as_evidenced_by }}
                        @endif
                    </p>
                @endif
            @endif

            <table class="data">
                <thead>
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th>{{ __('Order') }}</th>
                        <th style="width: 16%;">{{ __('Frequency') }}</th>
                        <th style="width: 14%;">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($diagnosis->orders->sortBy('sequence') as $order)
                        <tr>
                            <td>{{ $order->sequence }}</td>
                            <td>{{ $order->instruction }}</td>
                            <td>{{ $order->frequency ?: '—' }}</td>
                            <td>{{ $order->status?->getLabel() ?? $order->status }}</td>
                        </tr>
                        @foreach ($order->interventions as $intervention)
                            <tr>
                                <td></td>
                                <td colspan="3">
                                    <strong>{{ __('Intervention') }}:</strong> {{ $intervention->description }}
                                    <span class="muted">
                                        — {{ $intervention->performedBy?->name ?? __('Unknown') }}
                                        @if ($intervention->performed_at)
                                            ({{ $intervention->performed_at->format('Y-m-d H:i') }})
                                        @endif
                                    </span>
                                    @if ($intervention->notes)
                                        <br>{{ $intervention->notes }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr><td colspan="4" class="center muted">{{ __('No orders.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if ($diagnosis->objectives->isNotEmpty())
                <h3 style="margin-top: 10px;">{{ __('Objectives & evaluations') }}</h3>
                <table class="data">
                    <thead>
                        <tr>
                            <th>{{ __('Objective') }}</th>
                            <th style="width: 16%;">{{ __('Achievement') }}</th>
                            <th>{{ __('Latest evaluation') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($diagnosis->objectives as $objective)
                            @php($latest = $objective->evaluations->sortByDesc('evaluated_at')->first())
                            <tr>
                                <td>
                                    {{ $objective->description }}
                                    @if ($objective->target_date)
                                        <div class="muted">{{ __('Target') }}: {{ $objective->target_date->format('Y-m-d') }}</div>
                                    @endif
                                </td>
                                <td>{{ $objective->achievement_status?->getLabel() ?? $objective->achievement_status ?? '—' }}</td>
                                <td>
                                    @if ($latest)
                                        {{ $latest->outcome?->getLabel() ?? $latest->outcome }}
                                        — {{ $latest->evaluatedBy?->name ?? __('Unknown') }}
                                        ({{ $latest->evaluated_at?->format('Y-m-d H:i') }})
                                        @if ($latest->findings)
                                            <div>{{ $latest->findings }}</div>
                                        @endif
                                        @if ($latest->next_action)
                                            <div class="muted">{{ __('Next') }}: {{ $latest->next_action?->getLabel() ?? $latest->next_action }}</div>
                                        @endif
                                    @else
                                        <span class="muted">{{ __('Not evaluated') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <p class="muted">{{ __('No nursing diagnoses recorded.') }}</p>
    @endforelse

    <div class="footer">
        {{ __('Generated on') }} {{ now()->format('Y-m-d H:i') }}
        · {{ __('Care plan ID') }}: {{ $carePlan->id }}
    </div>
</body>
</html>
