<?php

use App\Http\Controllers\Goals\GoalController;
use App\Models\Goal;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('goal', fn (string $value) => Goal::query()->findOrFail($value));

    Route::patch('goals/reorder', [GoalController::class, 'reorder'])->name('goals.reorder');

    Route::resource('goals', GoalController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
});
