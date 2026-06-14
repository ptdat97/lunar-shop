<?php

namespace Modules\Theme\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Theme\Http\Middleware\InitStorefrontSession;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/theme.php'), 'theme');
    }

    /**
     * Bootstrap module: register the active theme's view namespace.
     *
     * Storefront controllers render via `view('theme::pages.product', $data)`.
     * The theme only renders — all data comes from module services / the API.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $active = config('theme.active', 'fashion');
        $base = base_path(config('theme.path', 'themes') . '/' . $active);

        View::addNamespace('theme', $base . '/views');

        // `storefront` middleware group = stateful web + Lunar storefront session.
        $router = $this->app->make(Router::class);
        $router->middlewareGroup('storefront', [
            'web',
            InitStorefrontSession::class,
        ]);
    }
}
