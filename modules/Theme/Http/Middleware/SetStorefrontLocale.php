<?php

namespace Modules\Theme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Theme\Services\LocaleService;

/**
 * Sets the application locale for storefront requests from the LocaleService
 * (session choice → browser preference → default), so all __() strings and
 * Lunar `translateAttribute()` resolve in the visitor's language.
 */
class SetStorefrontLocale
{
    public function __construct(protected LocaleService $locales)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        app()->setLocale($this->locales->resolve($request));

        return $next($request);
    }
}
