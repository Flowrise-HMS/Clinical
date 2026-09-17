<?php

namespace Modules\Clinical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Clinical\Classes\Services\Pdf\DischargeSummaryPdfService;
use Modules\Clinical\Models\DischargeSummary;

class DischargeSummaryPdfController
{
    public function __invoke(Request $request, DischargeSummary $dischargeSummary, DischargeSummaryPdfService $pdfs): Response
    {
        abort_unless($request->user()?->can('print_discharge_summary') ?? false, 403);

        $pdf = $pdfs->render($dischargeSummary);
        $filename = $pdfs->filename($dischargeSummary);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
