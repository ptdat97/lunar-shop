<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // MoMo IPN is a server-to-server POST from MoMo (no session/CSRF token);
        // it's authenticated by its HMAC signature instead.
        $middleware->validateCsrfTokens(except: [
            'payment/momo/ipn',
        ]);

        // Append `throttle:api` to the api middleware group. The `api` limiter
        // is defined in AppServiceProvider; credential endpoints add the
        // stricter `throttle:auth` per-route.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // R4 — one error envelope for every /api/v1/* failure, regardless of the
        // client's Accept header: { message, errors? }. Mirrors Laravel's own
        // validation shape so existing clients/tests keep working, and gives
        // headless consumers a single predictable error contract.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/v1/*')) {
                return null; // let web/SSR errors render normally
            }

            // Validation already emits { message, errors } — leave it to Laravel.
            if ($e instanceof ValidationException) {
                return null;
            }

            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $message = $e->getMessage();
            if ($message === '' || $status === 500) {
                // Never leak internals on a 500; give HTTP exceptions a default.
                $message = match ($status) {
                    401 => 'Unauthenticated.',
                    403 => 'This action is unauthorized.',
                    404 => 'Resource not found.',
                    405 => 'Method not allowed.',
                    429 => 'Too many requests.',
                    500 => 'Server error.',
                    default => 'Request failed.',
                };
            }

            return response()->json(['message' => $message], $status);
        });
    })->create();
