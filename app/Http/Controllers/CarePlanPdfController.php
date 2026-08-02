<?php

namespace Modules\Clinical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Clinical\Classes\Services\Pdf\CarePlanPdfService;
use Modules\Clinical\Models\CarePlan;

class CarePlanPdfController
{
    public function __invoke(Request $request, CarePlan $carePlan, CarePlanPdfService $pdfs): Response
    {
        abort_unless($request->user()?->can('view', $carePlan) ?? false, 403);

        $pdf = $pdfs->render($carePlan);
        $filename = $pdfs->filename($carePlan);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
