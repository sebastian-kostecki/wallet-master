<?php

use App\Http\Controllers\Categories\CategoryController;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('category', fn (string $value) => Category::query()->findOrFail($value));

    Route::patch(route_path('categories.reorder'), [CategoryController::class, 'reorder'])
        ->name('categories.reorder');

    Route::resource(route_path('categories'), CategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('categories')
        ->parameters([route_path('categories') => 'category']);

    Route::patch(route_path('categories.estimates.annual'), [CategoryController::class, 'saveAnnualEstimate'])
        ->name('categories.estimates.annual');

    Route::patch(route_path('categories.estimates.monthly'), [CategoryController::class, 'saveMonthlyEstimate'])
        ->name('categories.estimates.monthly');
});
