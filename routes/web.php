<?php

use Illuminate\Support\Facades\Route;
use Modules\Clinical\Http\Controllers\ClinicalController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('clinicals', ClinicalController::class)->names('clinical');
});
