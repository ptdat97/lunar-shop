<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Http\Middleware\EnsureTokenAbility;
use Modules\Core\Http\Middleware\ThrottleApiV1;
use Modules\Core\Http\Middleware\VerifyCsrfTokenUnlessStateless;
use Modules\Core\Support\ApiErrorResponse;

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

        // Cart + checkout live in the session-backed `storefront` group, so CSRF
        // applied to them — 419ing every mobile/POS write. A Bearer-token request
        // carries no ambient cookie credential, so there is nothing to forge; the
        // cookie storefront still goes through the full check.
        $middleware->replaceInGroup('web', ValidateCsrfToken::class, VerifyCsrfTokenUnlessStateless::class);

        // Rate-limit every `api/v1/*` request, whichever middleware group its
        // route sits in. throttleApi() is deliberately NOT used: it only covers
        // the framework `api` group, while cart/checkout/account routes live in
        // `web`/`storefront` (they need a session) — that left 48 of 52 API
        // routes, POST /api/v1/checkout among them, with no limiter at all.
        // Credential endpoints still add the stricter `throttle:auth` per-route.
        $middleware->prepend(ThrottleApiV1::class);

        // Scope bearer tokens to an ability. Not Sanctum's `abilities:` — that
        // one 401s any user without a currentAccessToken(), which turns ordinary
        // cookie-session requests into failures; ours defers to the session.
        $middleware->alias([
            'token.ability' => EnsureTokenAbility::class,
        ]);

        // Laravel defaults to `redirectGuestsTo(fn () => route('login'))`, and
        // `Authenticate` only skips it when the request `expectsJson()`. An API
        // client sending `Accept: */*` (curl's default, and many HTTP libraries)
        // therefore got a 302 to the HTML login page instead of
        // `401 {"message":"Unauthenticated."}`. Returning null for api/v1 lets the
        // AuthenticationException reach our JSON error envelope; storefront pages
        // keep redirecting to the login screen.
        // (The storefront's login page is named `storefront.login`; Laravel's
        // default callback hard-codes `route('login')`, which does not exist here.)
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/v1/*') ? null : route('storefront.login'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An API client is not a browser. Laravel decides "JSON or redirect?" from
        // the Accept header, so a client sending `Accept: */*` (curl's default,
        // and plenty of HTTP libraries) hit `Handler::unauthenticated()`'s
        // `redirect()->guest(route('login'))` branch and got an HTML login page —
        // or a 500 where that named route is unavailable — instead of
        // `401 {"message":"Unauthenticated."}`. Everything under api/v1 is JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/v1/*') || $request->expectsJson(),
        );
        // R4 — one error envelope for every /api/v1/* failure, regardless of the
        // client's Accept header: { message, errors? }. Mirrors Laravel's own
        // validation shape so existing clients/tests keep working, and gives
        // headless consumers a single predictable error contract.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->is('api/v1/*')) {
                return null; // let web/SSR errors render normally
            }

            // Validation already emits { message, errors } — leave it to Laravel.
            // (Skipped once the response is sent: there is nothing left to say.)
            if ($e instanceof ValidationException && ! headers_sent()) {
                return null;
            }

            return ApiErrorResponse::for($e, headers_sent());
        });
    })->create();
