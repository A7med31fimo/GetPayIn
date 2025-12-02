<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;


Route::prefix('api')->group(function () {

    // Product endpoint
    Route::get('products/{id}', [ProductController::class, 'show']);

    // Create hold
    Route::post('hold', [HoldController::class, 'store']);


    // Create order
    Route::post('orders', [OrderController::class, 'store']);

    // Payment webhook
    Route::post('payments/webhook', [PaymentWebhookController::class, 'handle']);
});
