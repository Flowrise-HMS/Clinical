<?php

namespace Modules\Clinical\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Modules\Clinical\Models\DailyWardStatement;

class DailyWardStatementPrintController extends Controller
{
    public function __invoke(DailyWardStatement $statement): View
    {
        $statement->load(['ward.nurseInCharge', 'branch', 'preparedBy', 'submittedBy', 'reviewedBy']);

        return view('clinical::clinical.workspace.pages.daily-ward-statement-print', [
            'statement' => $statement,
        ]);
    }
}
