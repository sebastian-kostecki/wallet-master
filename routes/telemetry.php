<?php

use App\Http\Controllers\Telemetry\TelemetryEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'throttle:api'])->group(function () {
    Route::post('/telemetry/event', [TelemetryEventController::class, 'store'])->name('telemetry.store');
});
