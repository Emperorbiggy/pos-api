<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OIRSController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    // Deliberately outside auth:api: refresh accepts an already-expired access
    // token, which the guard would reject before the controller could run.
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function (): void {
    Route::get('payments', [PaymentController::class, 'index']);

    Route::post('validate-ipn', [OIRSController::class, 'validateIpn']);
    Route::post('payment-notification', [OIRSController::class, 'paymentNotification']);
    Route::post('invoices', [OIRSController::class, 'generateInvoice']);
});
