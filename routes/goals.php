<?php

use App\Http\Controllers\Goals\GoalController;
use App\Models\Goal;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('goal', fn (string $value) => Goal::query()->findOrFail($value));

    Route::resource('goals', GoalController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::patch('goals/{goal}/estimates/annual', [GoalController::class, 'saveAnnualEstimate'])
        ->name('goals.estimates.annual');

    Route::patch('goals/{goal}/estimates/monthly', [GoalController::class, 'saveMonthlyEstimate'])
        ->name('goals.estimates.monthly');
});
