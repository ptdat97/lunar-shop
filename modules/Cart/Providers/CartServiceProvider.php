<?php

namespace Modules\Cart\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cart\Contracts\CartContract;
use Modules\Cart\Services\CartService;
use Modules\Theme\Support\LunarConfigOverride;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     *
     * Make the concrete service a singleton and resolve the CartContract to it
     * (alias) — so callers that type-hint either CartService or CartContract get
     * the SAME instance, and decorating it (Decorator::wrap) reaches both. No
     * caller change needed (D1).
     */
    public function register(): void
    {
        $this->app->singleton(CartService::class);
        $this->app->alias(CartService::class, CartContract::class);
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        // Re-apply cart_session override (auto_create) on top of Lunar's
        // published config/lunar/cart_session.php — survives --force.
        LunarConfigOverride::applyFrom('lunar.cart_session', __DIR__ . '/../Config/overrides.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
