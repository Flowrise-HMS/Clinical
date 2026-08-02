<?php

namespace Modules\Clinical\Classes\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Clinical\Models\CarePlan;

class CarePlanPdfService
{
    public function render(CarePlan $carePlan): string
    {
        $carePlan->loadMissing([
            'branch',
            'patient',
            'encounter',
            'author',
            'custodian',
            'medicalDiagnoses',
            'problems.strengths',
            'routineCares',
            'diagnoses.catalogue',
            'diagnoses.problem',
            'diagnoses.orders.interventions.performedBy',
            'diagnoses.objectives.evaluations.evaluatedBy',
        ]);

        return Pdf::loadView('clinical::pdf.care-plan', [
            'carePlan' => $carePlan,
        ])->setPaper('a4')->output();
    }

    public function filename(CarePlan $carePlan): string
    {
        $mrn = $carePlan->patient?->mrn ?? 'unknown';
        $shortId = str($carePlan->id)->substr(0, 8);

        return sprintf('care-plan-%s-%s.pdf', $mrn, $shortId);
    }
}
