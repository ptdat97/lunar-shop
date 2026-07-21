<?php

namespace Modules\Theme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Lunar\Facades\StorefrontSession;

/**
 * Initialise Lunar's storefront session (channel + customer groups) so pricing,
 * availability and customer-group rules resolve correctly. We inherit Lunar's
 * StorefrontSession — no reimplementation.
 */
class InitStorefrontSession
{
    public function handle(Request $request, Closure $next)
    {
        StorefrontSession::initChannel();
        StorefrontSession::initCustomerGroups();

        return $next($request);
    }
}
