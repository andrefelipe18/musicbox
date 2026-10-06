<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Symfony\Component\HttpFoundation\Response;

class RequireStatefulSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! EnsureFrontendRequestsAreStateful::fromFrontend($request) || ! $request->hasSession()) {
            return response()->json(['message' => 'A trusted stateful session is required.'], 419);
        }

        return $next($request);
    }
}
