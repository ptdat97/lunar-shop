<?php

namespace Modules\Menu\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Menu\Services\MenuRenderer;

class MenuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MenuRenderer::class);

        \Modules\Theme\Support\AdminPages::addResource(
            \Modules\Menu\Filament\Resources\MenuResource::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Expose the menu renderer to header/footer so Blade doesn't resolve a
        // service itself (coding standards §7).
        View::composer(
            ['theme::partials.header', 'theme::partials.footer'],
            fn ($view) => $view->with('menus', $this->app->make(MenuRenderer::class)),
        );
    }
}
