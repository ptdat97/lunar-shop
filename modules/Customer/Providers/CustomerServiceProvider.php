<?php

namespace Modules\Customer\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\Events\EventBridge;
use Modules\Platform\Support\Hooks;

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
     * Declare which Laravel auth events map onto the shared hook plane. The
     * Platform EventBridge owns the listen→doAction mechanism; we only state the
     * mapping (business knowledge). Covers every auth path (web SPA + API token),
     * so controllers stay free of cross-cutting side effects.
     */
    protected function bridgeAuthEvents(): void
    {
        $bridge = $this->app->make(EventBridge::class);

        $bridge->bridge(Registered::class, Hooks::CUSTOMER_REGISTERED, fn (Registered $e) => [$e->user]);
        $bridge->bridge(Login::class, Hooks::CUSTOMER_LOGGED_IN, fn (Login $e) => [$e->user]);
    }
}
