<?php

namespace Modules\FileManager\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\FileManager\Filament\Pages\MediaLibrary;
use Modules\FileManager\Services\FileManager;
use Modules\Theme\Support\AdminPages;

class FileManagerServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Contribute the Media Library page to Lunar's admin panel.
        AdminPages::add(MediaLibrary::class);
    }

    /**
     * Bootstrap module: views. Storage uses Lunar's Asset + Spatie media
     * tables, so this module ships no migrations of its own.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'filemanager');

        // Expose a file-URL resolver to the lookbook views so Blade doesn't
        // resolve the service itself (coding standards §7).
        View::composer(
            ['theme::pages.lookbook', 'theme::pages.lookbooks'],
            function ($view): void {
                $files = $this->app->make(FileManager::class);
                $view->with('fileUrl', fn ($file, string $size = 'large') => $files->url($file, $size));
            },
        );
    }
}
