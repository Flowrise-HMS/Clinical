<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinical\Http\Controllers\CarePlanPdfController;
use Modules\Clinical\Http\Controllers\ClinicalController;
use Modules\Clinical\Http\Controllers\DischargeSummaryPdfController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/care-plans/{carePlan}/pdf', CarePlanPdfController::class)
        ->name('clinical.care-plans.pdf');
    Route::get('/discharge-summaries/{dischargeSummary}/pdf', DischargeSummaryPdfController::class)
        ->name('clinical.discharge-summaries.pdf');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('clinicals', ClinicalController::class)->names('clinical');
});
