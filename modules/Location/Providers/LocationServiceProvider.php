<?php

namespace Modules\Location\Providers;

use Illuminate\Support\ServiceProvider;

class LocationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap: migrations + API routes for VN province/ward lookups.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
