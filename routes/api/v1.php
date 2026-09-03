<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OIRSController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\TerminalImportController;
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
        Route::match(['put', 'patch'], 'profile', [AuthController::class, 'updateProfile']);

        // PIN lives apart from registration and from the profile: an account can
        // exist without one, and a credential change should never ride along
        // with an ordinary profile edit.
        Route::post('pin', [AuthController::class, 'createPin']);
        Route::match(['put', 'patch'], 'pin', [AuthController::class, 'updatePin']);

        // Throttled: a 4 digit PIN is only 10,000 combinations, so an unlimited
        // endpoint would hand an attacker the PIN in seconds.
        Route::post('verify-pin', [AuthController::class, 'verifyPin'])
            ->middleware('throttle:10,1');
    });
});

Route::middleware('auth:api')->group(function (): void {
    // Creates accounts in bulk, so it is throttled: one upload can mint hundreds
    // of logins, and a runaway client should not be able to repeat that freely.
    // Admin only: one upload mints hundreds of logins and returns their
    // passwords, so an ordinary terminal must never reach it. Throttled too,
    // since a runaway client should not be able to repeat that freely.
    Route::post('terminals/import', [TerminalImportController::class, 'store'])
        ->middleware(['admin', 'throttle:5,1']);

    Route::get('payments', [PaymentController::class, 'index']);

    Route::post('validate-ipn', [OIRSController::class, 'validateIpn']);
    Route::post('payment-notification', [OIRSController::class, 'paymentNotification']);
    Route::post('invoices', [OIRSController::class, 'generateInvoice']);
    Route::get('invoices/{ipn}', [OIRSController::class, 'showInvoice'])
        ->where('ipn', '[A-Za-z0-9\-]{1,50}');
});
