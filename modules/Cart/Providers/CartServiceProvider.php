<?php

namespace Modules\Cart\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cart\Contracts\CartContract;
use Modules\Cart\Services\CartService;
use Modules\Theme\Support\LunarConfigOverride;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Cart state is server-side (Lunar CartSession); the service is a singleton
     * so one instance serves the request. CartContract aliases to it so callers
     * may type-hint either.
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
