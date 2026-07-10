<?php

namespace Modules\Customer\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Models\Customer;
use Modules\Core\Support\AdminPages;
use Modules\Customer\Filament\Pages\CustomerSettingsPage;
use Modules\Customer\Models\CustomerMeasurement;

class CustomerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Personal-access-token policy (TTL + abilities) for app/POS clients.
        $this->mergeConfigFrom(__DIR__.'/../Config/customer.php', 'customer');

        AdminPages::add(CustomerSettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, model relationships.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'customer');

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');

        // Size Intelligence v2: attach the saved measurement profile to Lunar's
        // Customer without forking the vendor model (extend, don't fork).
        Customer::resolveRelationUsing(
            'measurement',
            fn (Customer $customer) => $customer->hasOne(CustomerMeasurement::class, 'customer_id'),
        );
    }
}
