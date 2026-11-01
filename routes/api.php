<?php

use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'idempotent'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])->name('api.orders.store');
});
