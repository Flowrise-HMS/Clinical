<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinical\Http\Controllers\ClinicalController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('clinicals', ClinicalController::class)->names('clinical');
});
