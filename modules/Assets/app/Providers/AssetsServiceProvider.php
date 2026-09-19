<?php

namespace Modules\Assets\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Horizon\Horizon;
use Lunar\Core\Contracts\Actions\Media\AddsMedia;
use Lunar\Panel\Facades\Panel;
use Lunar\Panel\PanelManager;
use Modules\Assets\Actions\AddMediaThroughLibrary;
use Modules\Assets\Console\Commands\AdoptGalleryMedia;
use Modules\Assets\Console\Commands\MigrateLegacyImagesToLibrary;
use Modules\Assets\Console\Commands\RegenerateConversions;
use Modules\Assets\Panel\AssetsSection;
use Modules\Assets\Panel\MediaSettingsGroup;
use Modules\Assets\Services\ConversionGenerator;
use Modules\Assets\Services\HorizonSettings;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Assets\Services\MediaSettings;
use Modules\Assets\Services\MediaUrl;
use Modules\Assets\Support\Library\LibraryFilesystem;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Core\Support\LunarConfigOverride;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Filesystem;

class AssetsServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // One instance per request (scoped, so Octane-safe) so their in-object
        // memos — image sizes, conversion names, exists flags, resolved URLs —
        // hold across every view composer and API resource in the request. A
        // section-heavy page resolves hundreds of image URLs; without this each
        // resolution re-read settings + existence through the cache store,
        // which on a database cache store meant hundreds of DB round trips.
        $this->app->scoped(MediaSettings::class);
        $this->app->scoped(ConversionGenerator::class);
        $this->app->scoped(MediaUrl::class);

        // The library is the one source of truth for gallery images: every
        // upload into a Lunar gallery goes through it (LibraryLinks). Lunar
        // documents rebinding its action contracts as the way to do this.
        $this->app->bind(AddsMedia::class, AddMediaThroughLibrary::class);

        // Spatie resolves its Filesystem from the container; this one refuses
        // to rename or move the library's original through a link.
        $this->app->bind(Filesystem::class, LibraryFilesystem::class);
    }

    /**
     * Bootstrap module: migrations, views.
     */
    public function boot(): void
    {
        // File manager — trang riêng trên panel, và là lối DUY NHẤT để thêm ảnh:
        // mọi trường ảnh mở nó trong iframe rồi nhận lại file đã chọn.
        Panel::section(new AssetsSection);
        $this->shareFileManager();

        // Kích thước ảnh sinh ra — thứ FashionMediaDefinitions đọc.
        $this->app->make(SettingsRegistry::class)->add(new MediaSettingsGroup);

        // Re-apply our media definition overrides on top of Lunar's published
        // config/lunar/media.php — safe against `vendor:publish --force`.
        LunarConfigOverride::applyFrom('lunar.media', __DIR__.'/../../config/overrides.php');
        LunarConfigOverride::applyFrom('media-library', __DIR__.'/../../config/media-library-overrides.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'assets');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->applyHorizonSettings();
        $this->composeThemeImages();
        $this->composeLookbookFiles();
        $this->warmConversionsOnUpload();

        if ($this->app->runningInConsole()) {
            $this->commands([
                AdoptGalleryMedia::class,
                MigrateLegacyImagesToLibrary::class,
                RegenerateConversions::class,
            ]);
        }
    }

    /**
     * Tell every panel page where the file manager lives.
     *
     * Image fields sit on pages this module does not render — banners, pages,
     * theme settings, nested repeaters — and each opens the picker. Sharing the
     * URL keeps AssetsSection's route the only place it is written down,
     * instead of a path hard-coded into the bundle that silently 404s the day
     * the panel path or the prefix changes.
     *
     * Null for a staff member without the library's permission. Image fields
     * then disable their button, and Lunar's own gallery uploaders fall back
     * to the file dialog they ship with (nativeUploadBridge.js stands down) —
     * a product editor without content rights can still add product photos,
     * and those uploads still land in the library (AddMediaThroughLibrary
     * runs server-side, whoever uploads).
     *
     * Resolved per response, and only on Inertia responses — the storefront is
     * Blade and never sees it.
     */
    protected function shareFileManager(): void
    {
        Inertia::share('fileManager', function () {
            $staff = request()->user($this->app->make(PanelManager::class)->guard());

            if (! $staff?->can(AssetsSection::PERMISSION)) {
                return null;
            }

            return [
                'url' => route('panel.shop.media.picker'),
                'base' => route('panel.shop.media.index'),
                // Strings for what runs outside the manager's own page: the
                // popup's accessible name, and the bridge's notice.
                'labels' => [
                    'picker_title' => __('admin.file_manager.picker_title'),
                    'bridge_fetch_failed' => __('admin.file_manager.bridge_fetch_failed'),
                ],
            ];
        });
    }

    /**
     * Push admin-configured worker scaling ({@see HorizonSettings}) into the live
     * `horizon.*` config before Horizon reads it to launch its supervisors, so
     * maxProcesses/memory/timeout/tries are controllable from the panel instead
     * of only config/horizon.php.
     *
     * Both layers are overridden because Horizon merges an environment block over
     * the defaults: the `defaults.*` block sets memory/timeout/tries (which the
     * environment blocks don't repeat), and the `environments.{env}.*` block sets
     * maxProcesses (which Horizon takes from the environment, so overriding only
     * the default would be ignored in prod/local).
     *
     * Guarded so a CLI/test run without Horizon installed — or a Settings read
     * that fails at boot — never breaks the app; it just falls back to config.
     */
    protected function applyHorizonSettings(): void
    {
        if (! class_exists(Horizon::class)) {
            return;
        }

        try {
            $supervisors = $this->app->make(HorizonSettings::class)->supervisors();
        } catch (\Throwable $e) {
            return; // Settings/DB unavailable at boot — keep config defaults.
        }

        $env = $this->app->environment();

        foreach ($supervisors as $supervisor => $values) {
            foreach ($values as $field => $value) {
                config(["horizon.defaults.{$supervisor}.{$field}" => $value]);
            }

            // Horizon applies the environment block last; maxProcesses lives there.
            if (config("horizon.environments.{$env}.{$supervisor}") !== null) {
                config(["horizon.environments.{$env}.{$supervisor}.maxProcesses" => $values['maxProcesses']]);
            }
        }
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
            ['theme::pages.lookbook', 'theme::pages.lookbooks', 'theme::pages.page'],
            function ($view): void {
                $files = $this->app->make(MediaLibraryService::class);
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

            // Hover image: the first gallery image that isn't the primary
            // thumbnail, shown on card hover (fashion "flip to back view"). Only
            // resolved when `media` is already eager-loaded so a grid stays
            // N+1-free — grids that don't load media simply get no hover image.
            $hover = null;
            if ($product && $product->relationLoaded('media')) {
                $thumbId = $product->thumbnail?->id;
                $second = $product->media->first(fn ($m) => $m->id !== $thumbId);
                $hover = $second ? $urls->conversion($second, 'medium') : null;
            }

            // $image kept for parity with the JS-rendered grid (_card.js, which
            // gets a single thumbnail URL from the API). $picture adds the
            // responsive <picture> payload for the SSR card.
            $view->with([
                'image' => $product ? $urls->conversion($product->thumbnail, 'medium') : null,
                'picture' => $product ? $urls->responsive($product->thumbnail) : null,
                'hoverImage' => $hover,
            ]);
        });

        // Collection page: expose the collection's thumbnail as an OG image URL
        // (for a rich social preview) plus a wide banner image, without resolving
        // a service in Blade. Both use the same self-healing conversion URL; the
        // banner is null when the collection has no image so the view can skip it.
        View::composer('theme::pages.collection', function ($view): void {
            $collection = $view->getData()['collection'] ?? null;
            $image = app(MediaUrl::class)->conversion($collection?->thumbnail, 'large');
            $view->with([
                'ogImage' => $image,
                'bannerImage' => $collection?->thumbnail ? $image : null,
            ]);
        });

        // Checkout: expose a per-line thumbnail resolver so the order summary can
        // show product images (Shopify-style) without resolving a service inline.
        // The line's own colour when the variant has photos (MediaUrl::lineImage),
        // with every line's variant images loaded in one query up front.
        View::composer('theme::pages.checkout', function ($view): void {
            $urls = app(MediaUrl::class);
            ($view->getData()['cart'] ?? null)?->loadMissing('lines.purchasable.images');
            $view->with('lineImage', fn ($line, string $size = 'small') => $urls->lineImage($line, $size));
        });

        // Product page: zoom dimensions, OG image, and the gallery image set.
        View::composer('theme::pages.product', function ($view): void {
            $data = $view->getData();
            $product = $data['product'] ?? null;
            $media = $product?->media ?? collect();
            $urls = app(MediaUrl::class);

            // Render the gallery for the SELECTED variant (deep link
            // ?màu-sắc=Trắng, else the first variant), so the SSR page already shows
            // that colour's photos. Without this the page would paint the full
            // product gallery and enhance/product-variant.js would swap it on
            // load — a visible flash on every colour deep link.
            //
            // The selected variant's own photos are Lunar's variant images —
            // rows of this product's gallery, in the variant's order (the pivot
            // `position`), the same ones ProductVariantResource serialises for
            // the hydration payload. One source for both, so the SSR gallery and
            // the JS one can never disagree. No photos of its own → the whole
            // gallery.
            $own = $data['selectedVariant']?->images ?? collect();

            if ($own->isNotEmpty()) {
                $media = $own->values();
            }

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
