<?php

use App\Http\Controllers\Categories\CategoryController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('category', fn (string $value) => Category::query()->findOrFail($value));

    Route::resource('categories', CategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::patch('categories/{category}/estimates/annual', [CategoryController::class, 'saveAnnualEstimate'])
        ->name('categories.estimates.annual');

    Route::patch('categories/{category}/estimates/monthly', [CategoryController::class, 'saveMonthlyEstimate'])
        ->name('categories.estimates.monthly');
});
