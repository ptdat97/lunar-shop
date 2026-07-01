<?php

namespace Modules\Assets\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Assets\Filament\Pages\MediaImageSizes;
use Modules\Assets\Filament\Pages\MediaLibrary;
use Modules\Assets\Services\FileManager;
use Modules\Assets\Services\MediaSettings;
use Modules\Assets\Services\MediaUrl;
use Modules\Theme\Support\AdminPages;
use Modules\Theme\Support\LunarConfigOverride;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;

class AssetsServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Contribute the image-sizes + media-library pages to Lunar's admin panel.
        AdminPages::add(MediaImageSizes::class);
        AdminPages::add(MediaLibrary::class);
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
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'assets');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->composeThemeImages();
        $this->composeLookbookFiles();
        $this->warmConversionsOnUpload();
    }

    /**
     * When media is added (admin upload), pre-warm its conversions on the `media`
     * queue so the first storefront visitor doesn't pay the synchronous
     * generation cost. On-demand generation still covers anything not warmed yet.
     */
    protected function warmConversionsOnUpload(): void
    {
        Event::listen(MediaHasBeenAddedEvent::class, function (MediaHasBeenAddedEvent $event): void {
            app(MediaUrl::class)->warm($event->media);
        });
    }

    /**
     * Expose a file-URL resolver to the lookbook views so Blade doesn't resolve
     * the service itself (coding standards §7). Merged in from the former
     * FileManager module.
     */
    protected function composeLookbookFiles(): void
    {
        View::composer(
            ['theme::pages.lookbook', 'theme::pages.lookbooks'],
            function ($view): void {
                $files = $this->app->make(FileManager::class);
                $view->with('fileUrl', fn ($file, string $size = 'large') => $files->url($file, $size));
            },
        );
    }

    /**
     * Inject resolved image URLs into theme presentation views so Blade never
     * resolves a service itself (coding standards §7). Each composer reads the
     * model already passed into the view and adds a `$image` URL.
     */
    protected function composeThemeImages(): void
    {
        View::composer('theme::components.product-card', function ($view): void {
            $product = $view->getData()['product'] ?? null;
            $urls = app(MediaUrl::class);
            // $image kept for parity with the JS-rendered grid (_card.js, which
            // gets a single thumbnail URL from the API). $picture adds the
            // responsive <picture> payload for the SSR card.
            $view->with([
                'image' => $product ? $urls->conversion($product->thumbnail, 'medium') : null,
                'picture' => $product ? $urls->responsive($product->thumbnail) : null,
            ]);
        });

        View::composer('theme::sections.category-grid', function ($view): void {
            // The grid resolves a per-collection image inline; expose the helper
            // as a closure so the view stays free of service resolution.
            $urls = app(MediaUrl::class);
            $view->with('collectionImage', fn ($collection, string $size = 'small') =>
                $urls->conversion($collection?->thumbnail, $size));
        });

        // Checkout: expose a per-line thumbnail resolver so the order summary can
        // show product images (Shopify-style) without resolving a service inline.
        View::composer('theme::pages.checkout', function ($view): void {
            $urls = app(MediaUrl::class);
            $view->with('lineImage', fn ($line, string $size = 'small') =>
                $urls->conversion($line?->purchasable?->product?->thumbnail, $size));
        });

        // Product page: zoom dimensions, OG image, and the gallery image set.
        View::composer('theme::pages.product', function ($view): void {
            $product = $view->getData()['product'] ?? null;
            $media = $product?->media ?? collect();
            $urls = app(MediaUrl::class);

            $gallery = $media->map(fn ($image) => [
                'small' => $urls->conversion($image, 'small') ?? $urls->conversion($image, 'large'),
                'large' => $urls->conversion($image, 'large'),
                'zoom' => $urls->conversion($image, 'zoom') ?? $urls->conversion($image, 'large'),
                // Responsive payload for the main slide <picture> (LCP image).
                'picture' => $urls->responsive($image, ['small', 'medium', 'large'], 'large'),
            ])->filter(fn ($i) => $i['large'])->values();

            $view->with([
                'zoomSize' => app(MediaSettings::class)->sizes()['zoom'],
                'ogImage' => $urls->conversion($media->first(), 'large'),
                'galleryImages' => $gallery,
            ]);
        });
    }
}
