<?php

namespace Modules\Theme\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Support\AdminPages;
use Modules\Theme\Filament\Pages\ThemeSettingsPage;
use Modules\Theme\Http\Middleware\InitStorefrontSession;
use Modules\Theme\Http\Middleware\SetApiLocale;
use Modules\Theme\Http\Middleware\SetStorefrontLocale;
use Modules\Theme\Services\LocaleService;
use Modules\Theme\Services\ThemeSettings;

class ThemeServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/theme.php'), 'theme');

        // Scoped (one instance per request, Octane-safe) so the in-object memo
        // of theme settings holds across every layout partial in a request.
        $this->app->scoped(ThemeSettings::class);

        // Contribute the admin page (registered by ModulesServiceProvider).
        AdminPages::add(
            ThemeSettingsPage::class,
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
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $active = config('theme.active', 'fashion');
        $base = base_path(config('theme.path', 'themes').'/'.$active);

        View::addNamespace('theme', $base.'/views');

        // Admin (Filament) views for this module.
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'theme-admin');

        // Make theme settings available as $theme — only in storefront theme
        // views (the `theme::` namespace), never in admin/Filament views where
        // `$theme` already means the panel theme.
        View::composer('theme::*', function ($view) {
            $view->with('theme', $this->app->make(ThemeSettings::class));
        });

        // Email accent colour. `mail.default` is rendered by Laravel's mail
        // pipeline, not by a controller, so nothing pushed data into it and it
        // resolved ThemeSettings inline — exactly what §7 forbids.
        //
        // The sibling override `vendor/mail/html/header.blade.php` is a Blade
        // *component* (`@props`), which a View Composer does not reach; its logo
        // lookup stays inline. Verified: composing it blanks the logo.
        View::composer('mail.default', function ($view) {
            $view->with('accent', $this->app->make(ThemeSettings::class)->emailAccent());
        });

        // Language switcher data for the header (no service resolution in Blade).
        View::composer('theme::partials.header', function ($view) {
            $locales = $this->app->make(LocaleService::class);
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
            SetStorefrontLocale::class,
            InitStorefrontSession::class,
        ]);

        // Resolve the locale for every /api/v1 request (?locale= → Accept-Language
        // → default) so payloads (Lunar translateAttribute + __()) come back in
        // the client's language.
        //
        // Pushed onto BOTH groups: only 17 of the 52 API routes live in the
        // framework `api` group — cart, checkout, orders and account are
        // registered under `web`/`storefront` because Lunar's cart needs a
        // session, and so resolved no API locale at all. The middleware guards on
        // the `api/v1/*` URI, so it is a no-op for HTML pages, and it runs before
        // SetStorefrontLocale inside the `storefront` group (which is `web` +
        // extras) — a visitor's language switch still outranks `?locale=`.
        $router->pushMiddlewareToGroup('api', SetApiLocale::class);
        $router->pushMiddlewareToGroup('web', SetApiLocale::class);
    }
}
