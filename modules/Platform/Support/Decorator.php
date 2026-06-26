<?php

namespace Modules\Platform\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;

/**
 * Standardised decorator wiring for the platform. Wraps an existing container
 * binding (ideally a Contract/interface) with a decorator that receives the
 * inner instance — so a module/plugin can add behaviour without editing the
 * decorated service. Core owns only the MECHANISM; what to decorate is the
 * caller's business knowledge.
 *
 * Built on Laravel's container ->extend(), so:
 *  - decoration is lazy (runs when the binding is first resolved);
 *  - stacking is supported — decorate twice and the LAST registered wraps the
 *    outermost (extend wraps the current resolution), giving deterministic order.
 *
 * The decorator class's constructor takes the inner instance as its first
 * argument; any further constructor dependencies are auto-resolved from the
 * container.
 *
 *   Decorator::wrap(SearchEngine::class, BoostingSearchEngine::class);
 *   // -> resolving SearchEngine now returns new BoostingSearchEngine($inner, ...deps)
 */
class Decorator
{
    /**
     * Wrap $abstract's resolution with $decorator (inner passed as first ctor arg).
     *
     * @param  string  $abstract   the bound contract/class id being decorated
     * @param  class-string  $decorator  the wrapping class
     */
    public static function wrap(string $abstract, string $decorator, ?Container $container = null): void
    {
        $container ??= App::getFacadeRoot();

        $container->extend($abstract, function ($inner, $app) use ($decorator) {
            return $app->make($decorator, ['inner' => $inner]);
        });
    }

    /**
     * Wrap with a closure decorator: `fn($inner, $app) => new X($inner)`. Useful
     * when the decorator needs custom construction.
     *
     * @param  callable(mixed, Container): mixed  $using
     */
    public static function wrapUsing(string $abstract, callable $using, ?Container $container = null): void
    {
        $container ??= App::getFacadeRoot();

        $container->extend($abstract, fn ($inner, $app) => $using($inner, $app));
    }
}
