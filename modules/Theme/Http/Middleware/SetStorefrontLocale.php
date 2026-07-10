<?php

namespace Modules\Theme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Theme\Services\LocaleService;

/**
 * Sets the application locale for storefront **page** requests from the
 * LocaleService (session choice → browser preference → default), so all __()
 * strings and Lunar `translateAttribute()` resolve in the visitor's language.
 *
 * `/api/v1/*` requests are left to SetApiLocale, which runs earlier in the
 * `storefront` group: it already honours a visitor's stored language choice, and
 * additionally lets a headless client select one per request with `?locale=`.
 * Without this guard, cart and checkout — registered under `storefront` because
 * Lunar's cart needs a session — would silently overwrite that choice.
 */
class SetStorefrontLocale
{
    public function __construct(protected LocaleService $locales) {}

    public function handle(Request $request, Closure $next)
    {
        if (! $request->is('api/v1/*')) {
            app()->setLocale($this->locales->resolve($request));
        }

        return $next($request);
    }
}
