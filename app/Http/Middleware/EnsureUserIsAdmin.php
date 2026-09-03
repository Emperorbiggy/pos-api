<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureUserIsAdmin
{
    /**
     * Restrict a route to administrators.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('api');

        if ($user === null || $user->is_admin !== true) {
            return response()->json([
                'message' => 'This action requires an administrator account.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
