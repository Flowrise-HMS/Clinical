<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Discharge Summary') }} — {{ $summary->patient?->full_name ?? __('Patient') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        .muted { color: #6b7280; }
        .meta-grid { width: 100%; margin-top: 8px; }
        .meta-grid td { vertical-align: top; padding: 2px 8px 2px 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.data th { background: #f3f4f6; font-weight: 700; font-size: 10px; }
        .block { white-space: pre-wrap; }
        .watermark { position: fixed; top: 40%; left: 10%; font-size: 90px; color: rgba(220, 38, 38, 0.12); transform: rotate(-25deg); font-weight: 700; }
        .signature { margin-top: 28px; width: 100%; }
        .signature td { padding-top: 24px; border-top: 1px solid #9ca3af; width: 50%; }
        .footer { margin-top: 24px; color: #6b7280; font-size: 10px; }
    </style>
</head>
<body>
    @unless ($summary->isSigned())
        <div class="watermark">{{ __('DRAFT') }}</div>
    @endunless

    @include('core::print.partials.pdf-brand-header', [
        'branchId' => $summary->branch_id,
        'subtitle' => $summary->branch?->name,
    ])

    <h1>{{ __('Discharge Summary') }}</h1>
    <p class="muted">
        {{ $summary->status?->getLabel() }}
        @if ($summary->signed_at)
            · {{ __('signed :time by :name', ['time' => pdf_date($summary->signed_at), 'name' => $summary->signer?->name ?? '—']) }}
        @endif
    </p>

    @php $encounter = $summary->encounter; $patient = $summary->patient; @endphp

    <h2>{{ __('Patient & admission') }}</h2>
    <table class="meta-grid">
        <tr>
            <td><strong>{{ __('Patient') }}:</strong> {{ $patient?->full_name ?? '—' }}</td>
            <td><strong>{{ __('MRN') }}:</strong> {{ $patient?->mrn ?? '—' }}</td>
            <td><strong>{{ __('Age / Sex') }}:</strong> {{ $patient?->age !== null ? $patient->age.' yrs' : '—' }} / {{ is_object($patient?->gender) ? ($patient->gender->getLabel() ?? $patient->gender->value) : ($patient?->gender ?? '—') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Encounter') }}:</strong> {{ $encounter?->encounter_number ?? '—' }}</td>
            <td><strong>{{ __('Ward') }}:</strong> {{ $encounter?->location?->name ?? '—' }}</td>
            <td><strong>{{ __('Attending') }}:</strong> {{ $encounter?->admittedBy?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Admitted') }}:</strong> {{ $encounter?->admitted_at ? pdf_date($encounter->admitted_at) : '—' }}</td>
            <td><strong>{{ __('Discharged') }}:</strong> {{ $encounter?->discharged_at ? pdf_date($encounter->discharged_at) : __('pending') }}</td>
            <td><strong>{{ __('Length of stay') }}:</strong> {{ $encounter?->duration ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Disposition') }}:</strong> {{ $summary->disposition?->getLabel() ?? $encounter?->discharge_disposition?->getLabel() ?? '—' }}</td>
            <td><strong>{{ __('Condition at discharge') }}:</strong> {{ $summary->condition_at_discharge?->getLabel() ?? '—' }}</td>
            <td></td>
        </tr>
    </table>

    <h2>{{ __('Presenting complaint') }}</h2>
    <div class="block">{{ $summary->presenting_complaint ?: '—' }}</div>

    <h2>{{ __('Diagnoses') }}</h2>
    @if ($summary->admission_diagnosis)
        <p><strong>{{ __('On admission') }}:</strong> {{ $summary->admission_diagnosis }}</p>
    @endif
    @php $diagnoses = collect($summary->discharge_diagnoses ?? [])->filter(fn ($d) => filled($d['description'] ?? null)); @endphp
    @if ($diagnoses->isNotEmpty())
        <table class="data">
            <thead><tr><th>{{ __('Diagnosis') }}</th><th>{{ __('ICD-10') }}</th><th>{{ __('Type') }}</th><th>{{ __('Certainty') }}</th></tr></thead>
            <tbody>
                @foreach ($diagnoses as $diagnosis)
                    <tr>
                        <td>{{ $diagnosis['description'] }}</td>
                        <td>{{ $diagnosis['icd10_code'] ?? '—' }}</td>
                        <td>{{ ucfirst((string) ($diagnosis['type'] ?? '—')) }}</td>
                        <td>{{ ucfirst((string) ($diagnosis['certainty'] ?? '—')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ __('No discharge diagnoses recorded.') }}</p>
    @endif

    <h2>{{ __('Hospital course') }}</h2>
    <div class="block">{!! $summary->hospital_course ? nl2br(e(strip_tags($summary->hospital_course))) : '—' !!}</div>

    @if ($summary->procedures)
        <h2>{{ __('Procedures') }}</h2>
        <div class="block">{{ $summary->procedures }}</div>
    @endif

    <h2>{{ __('Discharge medications') }}</h2>
    @php $medications = collect($summary->discharge_medications ?? [])->filter(fn ($m) => filled($m['drug'] ?? null)); @endphp
    @if ($medications->isNotEmpty())
        <table class="data">
            <thead><tr><th>{{ __('Medication') }}</th><th>{{ __('Dose') }}</th><th>{{ __('Frequency') }}</th><th>{{ __('Route') }}</th><th>{{ __('Duration') }}</th><th>{{ __('Instructions') }}</th></tr></thead>
            <tbody>
                @foreach ($medications as $medication)
                    <tr>
                        <td>{{ $medication['drug'] }}</td>
                        <td>{{ $medication['dose'] ?? '—' }}</td>
                        <td>{{ strtoupper((string) ($medication['frequency'] ?? '—')) }}</td>
                        <td>{{ strtoupper((string) ($medication['route'] ?? '—')) }}</td>
                        <td>{{ isset($medication['duration_days']) ? $medication['duration_days'].' d' : '—' }}</td>
                        <td>{{ $medication['instructions'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ __('None.') }}</p>
    @endif

    <h2>{{ __('Instructions & follow-up') }}</h2>
    <div class="block">{!! $summary->instructions ? nl2br(e(strip_tags($summary->instructions))) : '—' !!}</div>
    <table class="meta-grid">
        <tr>
            <td><strong>{{ __('Diet') }}:</strong> {{ $summary->diet ?: '—' }}</td>
            <td><strong>{{ __('Activity') }}:</strong> {{ $summary->activity ?: '—' }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('Follow-up') }}:</strong> {{ $summary->follow_up_at ? pdf_date($summary->follow_up_at) : __('not scheduled') }}</td>
            <td><strong>{{ __('Notes') }}:</strong> {{ $summary->follow_up_notes ?: '—' }}</td>
        </tr>
    </table>

    <table class="signature">
        <tr>
            <td>{{ __('Clinician') }}: {{ $summary->signer?->name ?? $summary->author?->name ?? '' }}</td>
            <td>{{ __('Date') }}: {{ $summary->signed_at?->format('d M Y') ?? '' }}</td>
        </tr>
    </table>

    <p class="footer">{{ __('Generated :time', ['time' => pdf_date(now())]) }} · {{ config('app.name') }}@if ($footerText = app_settings()->pdfFooterText()) · {{ $footerText }}@endif</p>
</body>
</html>
