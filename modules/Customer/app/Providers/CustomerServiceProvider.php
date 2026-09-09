<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Core\Models\Customer;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Customer\Models\CustomerMeasurement;
use Modules\Customer\Panel\CustomerSettings;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Personal-access-token policy (TTL + abilities) for app/POS clients.
        $this->mergeConfigFrom(__DIR__.'/../../config/customer.php', 'customer');
    }

    /**
     * Bootstrap module: routes, migrations, model relationships.
     */
    public function boot(): void
    {
        // Nhóm cài đặt của module trên panel Lunar.
        $this->app->make(SettingsRegistry::class)->add(new CustomerSettings);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'customer');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Size Intelligence v2: attach the saved measurement profile to Lunar's
        // Customer without forking the vendor model (extend, don't fork).
        Customer::resolveRelationUsing(
            'measurement',
            fn (Customer $customer) => $customer->hasOne(CustomerMeasurement::class, 'customer_id'),
        );
    }
}
