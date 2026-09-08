<?php

namespace Modules\Shipping\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Core\Modifiers\ShippingModifiers;
use Modules\Shipping\Modifiers\FlatRateShippingModifier;
use Modules\Shipping\Modifiers\PickupShippingModifier;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + the shipping-zone admin resource.
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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'shipping-admin');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // add() returns void, so these are two statements — not a chain.
        $modifiers = $this->app->make(ShippingModifiers::class);
        $modifiers->add(FlatRateShippingModifier::class);
        // Adds itself only when the shop has configured a counter, so an
        // unconfigured install sees exactly the options it saw before.
        $modifiers->add(PickupShippingModifier::class);
    }
}
