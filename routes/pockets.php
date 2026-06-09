<?php

use App\Http\Controllers\Pockets\PocketController;
use App\Models\Pocket;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::bind('pocket', fn (string $value) => Pocket::query()->findOrFail($value));

    Route::patch(route_path('pockets.reorder'), [PocketController::class, 'reorder'])->name('pockets.reorder');

    Route::resource(route_path('pockets'), PocketController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->names('pockets')
        ->parameters([route_path('pockets') => 'pocket']);
});
