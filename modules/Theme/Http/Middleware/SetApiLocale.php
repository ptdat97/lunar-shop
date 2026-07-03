<?php

namespace Modules\Theme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Theme\Services\LocaleService;

/**
 * Sets the application locale for /api/v1 requests from the LocaleService
 * (`?locale=` query → Accept-Language → default), so Lunar `translateAttribute()`
 * and __() resolve in the client's language. Unlike the storefront middleware
 * this is session-less: a headless/app client selects the language per request.
 */
class SetApiLocale
{
    public function __construct(protected LocaleService $locales) {}

    public function handle(Request $request, Closure $next)
    {
        app()->setLocale($this->locales->resolveForApi($request));

        return $next($request);
    }
}
