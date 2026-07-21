<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scope a *bearer token* to an ability. Cookie sessions pass straight through.
 *
 * Sanctum ships `abilities:` / `ability:` for this, but both reject any request
 * whose user has no `currentAccessToken()` — they throw AuthenticationException
 * rather than deferring. The storefront authenticates through the `web` guard,
 * where Sanctum attaches a TransientToken only when its own guard resolves the
 * user, so those middlewares turn ordinary cookie requests into 401s depending
 * on how the session was established.
 *
 * The intent here is narrower and safer: a personal access token must carry the
 * ability, and nothing else changes. A POS token minted with `pos:*` therefore
 * gets a 403 on the customer surface instead of silently acting as the customer,
 * while the browser session — which is already authenticated by cookie + CSRF —
 * is untouched.
 */
class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->user()?->currentAccessToken();

        // No user, or a cookie session (TransientToken): not our business.
        // `auth:sanctum` on the route has already rejected true guests.
        if (! $token || $token instanceof TransientToken) {
            return $next($request);
        }

        if (! $token->can($ability)) {
            // Renders as 403 through the shared api/v1 error envelope.
            throw new AuthorizationException("Missing token ability [{$ability}].");
        }

        return $next($request);
    }
}
