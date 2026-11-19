<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinical\Http\Controllers\CarePlanPdfController;
use Modules\Clinical\Http\Controllers\DailyWardStatementPrintController;
use Modules\Clinical\Http\Controllers\DischargeSummaryPdfController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/care-plans/{carePlan}/pdf', CarePlanPdfController::class)
        ->name('clinical.care-plans.pdf');
    Route::get('/discharge-summaries/{dischargeSummary}/pdf', DischargeSummaryPdfController::class)
        ->name('clinical.discharge-summaries.pdf');
    Route::get('/ward-statements/{statement}/print', DailyWardStatementPrintController::class)
        ->name('clinical.ward-statements.print');
});
