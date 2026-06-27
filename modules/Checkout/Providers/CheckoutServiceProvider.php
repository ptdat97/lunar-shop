<?php

namespace Modules\Checkout\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Checkout\Contracts\CheckoutContract;
use Modules\Checkout\Services\CheckoutService;

class CheckoutServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings. Bind CheckoutContract to the concrete service
     * (singleton) + alias concrete → contract, so callers that type-hint
     * CheckoutService get the same (decoratable) instance. No caller change (D1).
     */
    public function register(): void
    {
        $this->app->singleton(CheckoutService::class);
        $this->app->alias(CheckoutService::class, CheckoutContract::class);
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
