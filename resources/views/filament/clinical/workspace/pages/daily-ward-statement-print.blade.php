<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Ward Statement — {{ $statement->ward?->name }} ({{ $statement->statement_date->format('d M Y') }})</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 24px; font-size: 13px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 18px 0 8px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 16px; border-bottom: 2px solid #111827; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .num { text-align: center; font-weight: 600; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-top: 28px; }
        .sig-box { border-top: 1px solid #374151; padding-top: 6px; font-size: 12px; }
        @media print { .no-print { display: none; } body { margin: 10px; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 12px; text-align: right;">
        <button onclick="window.print()" style="padding: 6px 14px; cursor: pointer;">Print Statement</button>
    </div>

    <div class="meta">
        <div>
            <h1>DAILY WARD STATEMENT (DAILY BED STATE)</h1>
            <div><strong>Facility / Branch:</strong> {{ $statement->branch?->name ?? config('app.name') }}</div>
            <div><strong>Ward:</strong> {{ $statement->ward?->name }} @if($statement->ward?->code) ({{ $statement->ward->code }}) @endif</div>
            <div><strong>Ward Incharge:</strong> {{ $statement->ward?->nurseInCharge?->name ?? '—' }}</div>
        </div>
        <div style="text-align: right;">
            <div><strong>Date:</strong> {{ $statement->statement_date->format('D, d M Y') }}</div>
            <div><strong>Period:</strong> {{ $statement->period_type->getLabel() }}</div>
            <div><strong>Status:</strong> {{ $statement->status->getLabel() }}</div>
            <div><strong>Window:</strong> {{ $statement->period_start_at->format('d M H:i') }} – {{ $statement->period_end_at->format('d M H:i') }}</div>
        </div>
    </div>

    <h2>1. Bed Complement &amp; Census Reconciliation</h2>
    <table>
        <thead>
            <tr>
                <th>Total Beds</th>
                <th>Blocked Beds</th>
                <th>Extra / Floor Beds</th>
                <th>Brought Forward</th>
                <th>Admissions</th>
                <th>Transfers In</th>
                <th>Discharges</th>
                <th>Transfers Out</th>
                <th>Deaths</th>
                <th>LAMA / Absconded</th>
                <th>On Pass</th>
                <th>Remaining</th>
                <th>Occupancy %</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ $statement->total_beds }}</td>
                <td class="num">{{ $statement->blocked_beds }}</td>
                <td class="num">{{ $statement->floor_or_extra_beds }}</td>
                <td class="num">{{ $statement->brought_forward }}</td>
                <td class="num">{{ $statement->admissions_count }}</td>
                <td class="num">{{ $statement->transfers_in_count }}</td>
                <td class="num">{{ $statement->discharges_count }}</td>
                <td class="num">{{ $statement->transfers_out_count }}</td>
                <td class="num">{{ $statement->deaths_count }}</td>
                <td class="num">{{ $statement->lama_absconded_count }}</td>
                <td class="num">{{ $statement->on_pass_count }}</td>
                <td class="num">{{ $statement->remaining_total }}</td>
                <td class="num">{{ $statement->occupancyRate() }}%</td>
            </tr>
        </tbody>
    </table>

    @if (! empty($statement->demographics_breakdown))
        <h2>2. Patient Demographics Breakdown</h2>
        <table>
            <thead>
                <tr>
                    <th>Male</th>
                    <th>Female</th>
                    <th>Under 5 yrs</th>
                    <th>5 – 14 yrs</th>
                    <th>15+ yrs (Adult)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="num">{{ $statement->demographics_breakdown['male'] ?? 0 }}</td>
                    <td class="num">{{ $statement->demographics_breakdown['female'] ?? 0 }}</td>
                    <td class="num">{{ $statement->demographics_breakdown['under_5'] ?? 0 }}</td>
                    <td class="num">{{ $statement->demographics_breakdown['age_5_14'] ?? 0 }}</td>
                    <td class="num">{{ $statement->demographics_breakdown['adult_15_plus'] ?? 0 }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>3. Clinical &amp; Operational Notes</h2>
    <table>
        <tbody>
            <tr>
                <th style="width: 28%;">Critically / Dangerously Ill List (DIL)</th>
                <td>{{ $statement->critically_ill_notes ?: 'None recorded' }}</td>
            </tr>
            <tr>
                <th>Special Cases (Police, Social Welfare, Maternal/Neonatal)</th>
                <td>{{ $statement->special_cases_notes ?: 'None recorded' }}</td>
            </tr>
            <tr>
                <th>Ward Incharge Remarks</th>
                <td>{{ $statement->general_remarks ?: 'None recorded' }}</td>
            </tr>
            @if ($statement->review_notes)
                <tr>
                    <th>Matron / Nursing Admin Review Notes</th>
                    <td>{{ $statement->review_notes }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-box">
            <strong>Prepared By:</strong> {{ $statement->preparedBy?->name ?? '—' }}<br>
            <span>Date: {{ $statement->created_at?->format('d M Y, H:i') }}</span>
        </div>
        <div class="sig-box">
            <strong>Submitted By (Ward Incharge):</strong> {{ $statement->submittedBy?->name ?? '—' }}<br>
            <span>Date: {{ $statement->submitted_at?->format('d M Y, H:i') ?? 'Pending' }}</span>
        </div>
        <div class="sig-box">
            <strong>Reviewed By (Matron / DDNS):</strong> {{ $statement->reviewedBy?->name ?? '—' }}<br>
            <span>Date: {{ $statement->reviewed_at?->format('d M Y, H:i') ?? 'Pending' }}</span>
        </div>
    </div>
</body>
</html>
