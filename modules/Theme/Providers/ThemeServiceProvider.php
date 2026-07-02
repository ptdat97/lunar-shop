<?php

namespace Modules\Theme\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Theme\Http\Middleware\InitStorefrontSession;
use Modules\Theme\Services\ThemeSettings;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/theme.php'), 'theme');

        $this->app->singleton(ThemeSettings::class);

        // Contribute the admin page (registered by ModulesServiceProvider).
        \Modules\Core\Support\AdminPages::add(
            \Modules\Theme\Filament\Pages\ThemeSettingsPage::class,
        );
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

        // Admin (Filament) views for this module.
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'theme-admin');

        // Make theme settings available as $theme — only in storefront theme
        // views (the `theme::` namespace), never in admin/Filament views where
        // `$theme` already means the panel theme.
        View::composer('theme::*', function ($view) {
            $view->with('theme', $this->app->make(ThemeSettings::class));
        });

        // Language switcher data for the header (no service resolution in Blade).
        View::composer('theme::partials.header', function ($view) {
            $locales = $this->app->make(\Modules\Theme\Services\LocaleService::class);
            $view->with([
                'storefrontLocales' => $locales->supported(),
                'currentLocale' => $this->app->getLocale(),
                'showLanguageSwitcher' => $locales->showSwitcher(),
            ]);
        });

        // `storefront` middleware group = stateful web + storefront locale +
        // Lunar storefront session. SetStorefrontLocale runs after `web` (it
        // reads the session) so __() + translateAttribute() use the visitor's
        // chosen language.
        $router = $this->app->make(Router::class);
        $router->middlewareGroup('storefront', [
            'web',
            \Modules\Theme\Http\Middleware\SetStorefrontLocale::class,
            InitStorefrontSession::class,
        ]);
    }
}
