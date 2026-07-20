<?php

namespace Modules\Theme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Theme\Services\LocaleService;

/**
 * Resolves the locale for every `/api/v1/*` request: `?locale=` query →
 * `Accept-Language` → store default, so Lunar `translateAttribute()` and `__()`
 * answer in the client's language.
 *
 * Guarded by URI rather than by middleware group. Only 17 of the 52 API routes
 * sat in the framework `api` group this used to be pushed onto; cart, checkout,
 * orders and account live in `web`/`storefront` because they need a session, and
 * so never resolved an API locale at all.
 *
 * A visitor who picked a language on the storefront keeps it: their session
 * choice wins over `Accept-Language`, and this middleware steps aside rather
 * than letting `?locale=` override the language they selected in the UI.
 */
class SetApiLocale
{
    public function __construct(protected LocaleService $locales) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/*')) {
            app()->setLocale($this->localeFor($request));
        }

        return $next($request);
    }

    /**
     * A language the visitor picked in the storefront UI (persisted to the
     * session) outranks `?locale=` on an XHR from that same page — otherwise a
     * stray query string would silently flip the language they chose. Headless
     * clients have no session, so they always get `resolveForApi()`.
     */
    protected function localeFor(Request $request): string
    {
        $chosen = $request->hasSession() ? $request->session()->get('locale') : null;

        return $this->locales->isSupported($chosen)
            ? $chosen
            : $this->locales->resolveForApi($request);
    }
}
