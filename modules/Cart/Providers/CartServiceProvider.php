<?php

namespace Modules\Cart\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Cart\Services\CartService;
use Modules\Theme\Support\LunarConfigOverride;

class CartServiceProvider extends ServiceProvider
{
    /**
     * Cart state is server-side (Lunar CartSession); the service is a singleton
     * so one instance serves the request.
     */
    public function register(): void
    {
        $this->app->singleton(CartService::class);
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
