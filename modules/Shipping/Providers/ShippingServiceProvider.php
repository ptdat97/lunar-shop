<?php

namespace Modules\Shipping\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Modules\Shipping\Modifiers\FlatRateShippingModifier;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/shipping.php'), 'shipping');
    }

    /**
     * Bootstrap module: register shipping options into Lunar's manifest.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->app->make(ShippingModifiers::class)
            ->add(FlatRateShippingModifier::class);
    }
}
