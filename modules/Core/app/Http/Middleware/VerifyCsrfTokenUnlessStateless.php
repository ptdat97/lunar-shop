<?php

namespace Modules\Core\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Modules\Checkout\Services\TokenAwareCartSession;

/**
 * CSRF, except for genuinely stateless requests.
 *
 * Cart and checkout run in the `web`-derived `storefront` group because Lunar's
 * cart needs a session, which drags CSRF along. That is correct for the cookie
 * storefront but 419s every mobile/POS write: those clients have no cookie, so
 * no XSRF token to echo back.
 *
 * CSRF exists to stop a third-party page riding the user's *ambient* cookie
 * credentials. Neither exempt case carries one:
 *
 *  - `Authorization: Bearer …` is never attached cross-site by a browser.
 *  - `X-Cart-Token` is a custom header, so a cross-origin request must clear a
 *    CORS preflight first, and `cors.supports_credentials` is false — the
 *    session cookie cannot ride along even if it did.
 *
 * The cookie storefront is unaffected: it sends neither header, and a request
 * that *does* carry a session user is never exempted, so a logged-in browser
 * cannot opt out of CSRF just by adding a header.
 */
class VerifyCsrfTokenUnlessStateless extends ValidateCsrfToken
{
    /**
     * Exempt stateless clients, on top of the configured URI exceptions (e.g.
     * the MoMo IPN callback, which authenticates by HMAC signature).
     */
    protected function inExceptArray($request): bool
    {
        return $this->isStateless($request) || parent::inExceptArray($request);
    }

    /**
     * A request with no cookie-session identity that identifies itself by a
     * header the browser will not send ambiently.
     */
    protected function isStateless($request): bool
    {
        // A cookie-authenticated visitor is the storefront: never exempt, or a
        // cross-site page could skip CSRF simply by setting the cart header.
        if ($request->hasSession() && $request->user()) {
            return false;
        }

        return TokenAwareCartSession::isStatelessRequest($request);
    }
}
