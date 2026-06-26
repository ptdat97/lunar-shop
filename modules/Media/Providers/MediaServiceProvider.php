<?php

namespace Modules\Media\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Media\Filament\Pages\MediaImageSizes;
use Modules\Media\Services\MediaSettings;
use Modules\Media\Services\MediaUrl;
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

        $this->composeThemeImages();
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
            $view->with('image', $product
                ? app(MediaUrl::class)->conversion($product->thumbnail, 'medium')
                : null);
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
            ])->filter(fn ($i) => $i['large'])->values();

            $view->with([
                'zoomSize' => app(MediaSettings::class)->sizes()['zoom'],
                'ogImage' => $urls->conversion($media->first(), 'large'),
                'galleryImages' => $gallery,
            ]);
        });
    }
}
