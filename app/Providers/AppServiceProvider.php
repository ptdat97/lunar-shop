<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The Lunar admin panel is registered by ModulesServiceProvider after all
     * module providers have registered, so modules can contribute admin pages.
     * Shared infrastructure (settings, queues, admin pages) lives in the Core
     * module (Modules\Core), registered first by ModulesServiceProvider.
     */
    public function register(): void
    {
        //
    }
}
