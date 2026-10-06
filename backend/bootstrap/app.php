<?php

use App\Http\Middleware\RequireAdminSession;
use App\Http\Middleware\RequireStatefulSession;
use App\Music\Exceptions\MusicProviderException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'stateful-session' => RequireStatefulSession::class,
            'admin-session' => RequireAdminSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $exception->errors(),
                ], 422);
            }

            if ($exception instanceof MusicProviderException) {
                return response()->json(['message' => 'Music provider is temporarily unavailable.'], 503);
            }

            $status = match (true) {
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof TokenMismatchException => 419,
                $exception instanceof ModelNotFoundException,
                $exception instanceof NotFoundHttpException => 404,
                $exception instanceof ThrottleRequestsException => 429,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };
            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'Forbidden.',
                404 => 'Not found.',
                419 => 'CSRF token mismatch.',
                429 => 'Too many requests.',
                503 => 'Service temporarily unavailable.',
                405 => 'Method not allowed.',
                500 => 'Server error.',
                default => 'Request failed.',
            };
            $headers = $exception instanceof HttpExceptionInterface
                ? $exception->getHeaders()
                : [];

            return response()->json(['message' => $message], $status, $headers);
        });
    })->create();
