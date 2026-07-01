<?php

namespace Modules\Shipping\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\ShippingModifiers;
use Modules\Shipping\Filament\Resources\ShippingZoneResource;
use Modules\Shipping\Modifiers\FlatRateShippingModifier;
use Modules\Theme\Support\AdminPages;

class ShippingServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings + the shipping-zone admin resource.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/shipping.php'), 'shipping');

        AdminPages::addResource(ShippingZoneResource::class);
        AdminPages::add(\Modules\Shipping\Filament\Pages\ShippingSettingsPage::class);
    }

    /**
     * Bootstrap module: register shipping options into Lunar's manifest.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'shipping-admin');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->app->make(ShippingModifiers::class)
            ->add(FlatRateShippingModifier::class);
    }
}
