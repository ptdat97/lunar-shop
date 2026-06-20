<?php

namespace Modules\Media\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Media\Filament\Pages\MediaImageSizes;
use Modules\Theme\Support\AdminPages;
use Modules\Theme\Support\LunarConfigOverride;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Contribute the Image Sizes page to Lunar's admin panel.
        AdminPages::add(MediaImageSizes::class);
    }

    /**
     * Bootstrap module: migrations, views.
     */
    public function boot(): void
    {
        // Re-apply our media definition overrides on top of Lunar's published
        // config/lunar/media.php — safe against `vendor:publish --force`.
        LunarConfigOverride::applyFrom('lunar.media', __DIR__ . '/../Config/overrides.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'media');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
    }
}
