<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The Lunar admin panel is registered by ModulesServiceProvider after all
     * module providers have registered, so modules can contribute admin pages.
     */
    public function register(): void
    {
        //
    }
}
