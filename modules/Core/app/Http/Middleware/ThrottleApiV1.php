<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits every `api/v1/*` request, whatever middleware group its route was
 * registered in.
 *
 * `throttleApi()` only covers the framework `api` group, but our API routes are
 * spread across the `api`, `web` and `storefront` groups (cart/checkout need a
 * session, account endpoints need the SPA cookie). That left most of the API —
 * including `POST /api/v1/checkout` — with no limiter at all. Guarding on the
 * URI instead of the group means a new module route is covered by default and
 * cannot silently opt out.
 *
 * Routes that already declare their own limiter (e.g. `throttle:auth` on the
 * credential endpoints) keep it: this adds the baseline `api` limiter on top,
 * and the stricter one still wins because it rejects first.
 */
class ThrottleApiV1
{
    public function __construct(
        protected ThrottleRequests $throttle,
    ) {}

    /** Paths that must stay reachable even when the limiter's backing store is down. */
    protected const EXEMPT = ['api/v1/health'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/*') || $request->is(...self::EXEMPT)) {
            // The rate limiter is cache-backed: throttling the health probe
            // would make it 500 during the very cache outage it exists to
            // report, and an orchestrator must never be rate limited anyway.
            return $next($request);
        }

        $limiter = $this->limiterFor($request);

        // Delegate to Laravel's own throttle so the 429 response and the
        // X-RateLimit-* headers stay identical to a route-level `throttle:`.
        return $this->throttle->handle($request, $next, $limiter);
    }

    /**
     * Order placement is the expensive, abuse-prone write on this API — it
     * creates an order, reserves stock and talks to a gateway. Give it a
     * tighter bucket than reads.
     */
    protected function limiterFor(Request $request): string
    {
        return $request->isMethod('POST') && $request->is('api/v1/checkout')
            ? 'checkout'
            : 'api';
    }
}
