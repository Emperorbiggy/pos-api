<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Services\OIRS\Exceptions\OIRSException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Apply CORS to ALL routes, not just API routes
        $middleware->use([
            HandleCors::class,
        ]);

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'admin' => EnsureUserIsAdmin::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (OIRSException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Relay the statuses that describe the caller's request, so a POS can
            // act on them. Anything else — OIRS rejecting OUR app key, OIRS being
            // down, no status at all — is an upstream problem the caller can do
            // nothing about, and must not be dressed up as their fault: relaying
            // a 401 here would read as an expired JWT and log the cashier out.
            $relayable = [400, 404, 409, 422];

            return response()->json([
                'message' => $exception->getMessage(),
            ], in_array($exception->getCode(), $relayable, true)
                ? $exception->getCode()
                : Response::HTTP_BAD_GATEWAY);
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $exception->errors(),
            ], 422);
        });
    })->create();
