<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // Horizon's dashboard runs on the default (customer) guard, but access
        // belongs to back-office staff: allow Lunar admin staff (the Filament
        // panel's `staff` guard), same people who can see the Lunar admin.
        Gate::define('viewHorizon', function ($user = null) {
            return (bool) Auth::guard('staff')->user()?->admin;
        });
    }
}
