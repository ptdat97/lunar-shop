<?php

namespace Modules\FileManager\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\FileManager\Filament\Pages\MediaLibrary;
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
    }
}
