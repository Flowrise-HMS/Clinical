<?php

namespace Modules\Clinical\Classes\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Clinical\Models\DischargeSummary;

class DischargeSummaryPdfService
{
    public function render(DischargeSummary $summary): string
    {
        return Pdf::loadView('clinical::pdf.discharge-summary', [
            'summary' => $this->prepare($summary),
        ])->setPaper('a4')->output();
    }

    /**
     * Loads everything the template needs (kept separate so tests can render
     * the Blade without producing a PDF).
     */
    public function prepare(DischargeSummary $summary): DischargeSummary
    {
        return $summary->loadMissing([
            'branch',
            'patient',
            'encounter.location',
            'encounter.admittedBy',
            'encounter.dischargedBy',
            'author',
            'signer',
        ]);
    }

    public function filename(DischargeSummary $summary): string
    {
        $mrn = $summary->patient?->mrn ?? 'unknown';
        $shortId = str($summary->id)->substr(0, 8);

        return sprintf('discharge-summary-%s-%s.pdf', $mrn, $shortId);
    }
}
