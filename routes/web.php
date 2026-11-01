<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\OrderCancellationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::post('/orders/{order}/cancel', [OrderCancellationController::class, 'store'])->name('orders.cancel');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/refund', [Admin\OrderRefundController::class, 'store'])->name('orders.refund');
    });
});
