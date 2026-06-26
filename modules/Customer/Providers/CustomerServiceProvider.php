<?php

namespace Modules\Customer\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;

class CustomerServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->bridgeAuthEvents();
    }

    /**
     * Re-broadcast Laravel's framework auth events onto the shared hook plane so
     * other modules/plugins react via Hooks::* without coupling to Laravel's
     * event classes. One central producer for every auth path (web SPA + API
     * token) — controllers stay free of cross-cutting side effects.
     */
    protected function bridgeAuthEvents(): void
    {
        Event::listen(Registered::class, function (Registered $event): void {
            Hook::doAction(Hooks::CUSTOMER_REGISTERED, [$event->user]);
        });

        Event::listen(Login::class, function (Login $event): void {
            Hook::doAction(Hooks::CUSTOMER_LOGGED_IN, [$event->user]);
        });
    }
}
