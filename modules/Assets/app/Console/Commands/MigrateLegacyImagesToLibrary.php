<?php

namespace Modules\Assets\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Lunar\Models\Asset;
use Lunar\Models\Product;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Catalog\Models\ProductSku;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Menu;
use Modules\Content\Models\Page;
use Modules\Content\Models\PageSection;
use Modules\Theme\Services\ThemeSettings;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * One-off, idempotent port of every image field that used to accept a direct
 * upload (a bare path on the `media` disk, or — for product variant swatches
 * and SKU photos — a Spatie Media id attached straight to the Product) into a
 * proper Media Library Asset, rewriting the owning column/JSON key to the
 * resulting Asset id.
 *
 * After this app-wide FileUpload -> MediaPicker migration, every image field
 * is a Select bound to a library Asset id; this command is what makes
 * existing (pre-migration) data valid input for those pickers again. Re-runnable:
 * a value that is already numeric (an Asset id) is left untouched, so running
 * it twice — or against a partially-migrated database — is safe.
 */
class MigrateLegacyImagesToLibrary extends Command
{
    protected $signature = 'assets:migrate-legacy-images {--dry-run : Report what would change without writing}';

    protected $description = 'Port legacy direct-upload image paths/media ids into Media Library Assets';

    /** Path (relative to the `media` disk) => Asset id, so the same file is never ingested twice. */
    private array $pathToAssetId = [];

    /** Spatie Media id => Asset id, so the same media item is never re-owned twice. */
    private array $mediaIdToAssetId = [];

    private bool $dryRun = false;

    private int $created = 0;

    public function handle(MediaLibraryService $library): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $this->migrateBanners();
        $this->migratePages();
        $this->migrateMenuItems();
        $this->migratePageSections();
        $this->migrateThemeSettings();
        $this->migrateProductVariables();
        $this->migrateProductSkuImages();

        $this->info(($this->dryRun ? '[dry-run] ' : '')."Done. {$this->created} new library asset(s) created.");

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------------
    // Sources: bare disk paths -> Asset
    // ---------------------------------------------------------------------

    protected function migrateBanners(): void
    {
        Banner::query()->each(function (Banner $banner) {
            $image = $this->pathToAsset($banner->image);
            $mobile = $this->pathToAsset($banner->mobile_image);

            $this->saveIfChanged($banner, [
                'image' => $image ?? $banner->image,
                'mobile_image' => $mobile ?? $banner->mobile_image,
            ]);
        });
    }

    protected function migratePages(): void
    {
        Page::query()->each(function (Page $page) {
            $image = $this->pathToAsset($page->featured_image);

            $this->saveIfChanged($page, ['featured_image' => $image ?? $page->featured_image]);
        });
    }

    protected function migrateMenuItems(): void
    {
        Menu::query()->with('items')->each(function (Menu $menu) {
            foreach ($menu->items as $item) {
                $image = $this->pathToAsset($item->image);

                $this->saveIfChanged($item, ['image' => $image ?? $item->image]);
            }
        });
    }

    /**
     * `page_sections.settings` is a free-form JSON blob whose shape depends on
     * `type`. Only the known image keys per type are touched; everything else
     * passes through untouched.
     */
    protected function migratePageSections(): void
    {
        PageSection::query()->each(function (PageSection $section) {
            $settings = $section->settings ?? [];
            $changed = false;

            if ($section->type === 'hero-slider') {
                $changed = $this->rewriteListKey($settings, 'slides', 'image') || $changed;
            }

            if ($section->type === 'lookbook') {
                $changed = $this->rewriteListKey($settings, 'slides', 'banner') || $changed;
                $changed = $this->rewriteListKey($settings, 'slides', 'pin_image') || $changed;
            }

            if ($section->type === 'collection-grid') {
                $changed = $this->rewriteListKey($settings, 'items', 'image') || $changed;
            }

            if ($changed) {
                $this->saveIfChanged($section, ['settings' => $settings]);
            }
        });
    }

    /**
     * Rewrite `$settings[$listKey][*][$imageKey]` in place from a bare path to
     * an Asset id. Returns whether anything changed.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function rewriteListKey(array &$settings, string $listKey, string $imageKey): bool
    {
        $changed = false;

        foreach ($settings[$listKey] ?? [] as $i => $row) {
            $asset = $this->pathToAsset($row[$imageKey] ?? null);

            if ($asset !== null) {
                $settings[$listKey][$i][$imageKey] = $asset;
                $changed = true;
            }
        }

        return $changed;
    }

    protected function migrateThemeSettings(): void
    {
        $settings = app(ThemeSettings::class);
        $all = $settings->all();

        $general = $all['general'] ?? [];
        $generalChanged = false;
        foreach (['logo', 'logo_footer'] as $key) {
            if ($asset = $this->pathToAsset($general[$key] ?? null)) {
                $general[$key] = $asset;
                $generalChanged = true;
            }
        }
        if ($generalChanged) {
            $this->writeSettingsGroup($settings, 'general', $general);
        }

        $brand = $all['brand'] ?? [];
        if ($asset = $this->pathToAsset($brand['email_logo'] ?? null)) {
            $brand['email_logo'] = $asset;
            $this->writeSettingsGroup($settings, 'brand', $brand);
        }

        $payment = $all['payment'] ?? [];
        $paymentChanged = false;
        foreach ($payment as $i => $path) {
            if ($asset = $this->pathToAsset($path)) {
                $payment[$i] = $asset;
                $paymentChanged = true;
            }
        }
        if ($paymentChanged) {
            $this->writeSettingsGroup($settings, 'payment', array_values($payment));
        }
    }

    protected function writeSettingsGroup(ThemeSettings $settings, string $group, array $value): void
    {
        $this->line(($this->dryRun ? '[dry-run] ' : '')."theme_settings.{$group}: migrating image field(s)");

        if (! $this->dryRun) {
            $settings->set($group, $value);
        }
    }

    // ---------------------------------------------------------------------
    // Sources: Spatie Media ids already attached to a Product -> Asset
    // (variant image-swatches + per-SKU photos)
    // ---------------------------------------------------------------------

    protected function migrateProductVariables(): void
    {
        Product::query()->withTrashed()->chunkById(50, function ($products) {
            foreach ($products as $product) {
                $variables = $product->variables ?? [];
                $changed = false;

                foreach ($variables as $ai => $axis) {
                    if (($axis['display_type'] ?? null) !== 'image') {
                        continue;
                    }

                    foreach ($axis['values'] ?? [] as $vi => $value) {
                        $asset = $this->mediaIdToAsset($value['image'] ?? null);

                        if ($asset !== null) {
                            $variables[$ai]['values'][$vi]['image'] = $asset;
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    $this->saveIfChanged($product, ['variables' => $variables]);
                }
            }
        });
    }

    protected function migrateProductSkuImages(): void
    {
        ProductSku::query()->withTrashed()->chunkById(200, function ($skus) {
            foreach ($skus as $sku) {
                $ids = collect($sku->images ?? []);
                $rewritten = $ids->map(fn ($id) => $this->mediaIdToAsset($id) ?? $id)->all();

                if ($rewritten !== $ids->all()) {
                    $this->saveIfChanged($sku, ['images' => $rewritten]);
                }
            }
        });
    }

    // ---------------------------------------------------------------------
    // Shared resolution helpers
    // ---------------------------------------------------------------------

    /**
     * Resolve a bare `media`-disk path to an Asset id, ingesting it into a new
     * library Asset the first time it's seen. Returns null for anything that
     * is already an Asset id (numeric), empty, an absolute URL, or missing on
     * disk — the caller keeps the original value in those cases.
     */
    protected function pathToAsset(mixed $path): ?int
    {
        if (empty($path) || is_numeric($path) || ! is_string($path)) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null; // External URL — nothing to ingest.
        }

        $relative = ltrim($path, '/');

        if (isset($this->pathToAssetId[$relative])) {
            return $this->pathToAssetId[$relative];
        }

        if (! Storage::disk('media')->exists($relative)) {
            $this->warn("Skipping missing file: {$relative}");

            return null;
        }

        if ($this->dryRun) {
            $this->created++;

            // Fabricate a stable placeholder so repeated look-ups within the
            // same dry-run stay consistent; never persisted.
            return $this->pathToAssetId[$relative] = -$this->created;
        }

        $asset = app(MediaLibraryService::class)->storeFromPath(
            Storage::disk('media')->path($relative),
            meta: ['name' => pathinfo($relative, PATHINFO_FILENAME)],
        );

        $this->created++;

        return $this->pathToAssetId[$relative] = $asset->id;
    }

    /**
     * Resolve a Spatie Media id (already attached to a Product's `images` or
     * `swatch` collection) to a library Asset id, MOVING that Media row onto a
     * new Asset (re-owning it, not copying the file) the first time it's seen.
     * Returns null for anything that isn't a positive numeric media id, or that
     * no longer resolves to an existing Media row.
     */
    protected function mediaIdToAsset(mixed $mediaId): ?int
    {
        if (! is_numeric($mediaId) || (int) $mediaId <= 0) {
            return null;
        }

        $mediaId = (int) $mediaId;

        if (isset($this->mediaIdToAssetId[$mediaId])) {
            return $this->mediaIdToAssetId[$mediaId];
        }

        $media = Media::find($mediaId);

        if (! $media) {
            return null;
        }

        if ($this->dryRun) {
            $this->created++;

            return $this->mediaIdToAssetId[$mediaId] = -$this->created;
        }

        $asset = Asset::create([]);
        $media->model_type = $asset->getMorphClass();
        $media->model_id = $asset->id;
        $media->collection_name = config('lunar.media.collection', 'images');
        $media->save();

        $this->created++;

        return $this->mediaIdToAssetId[$mediaId] = $asset->id;
    }

    /**
     * Save a model's changed attributes, respecting --dry-run (report only).
     *
     * Attributes are set directly (not via fill()) because some target models —
     * notably Lunar's Product, whose `variables` column this command also
     * rewrites — do not list every touched column in $fillable; fill() would
     * silently drop those and this command would report success while writing
     * nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function saveIfChanged(Model $model, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $model->setAttribute($key, $value);
        }

        if (! $model->isDirty()) {
            return;
        }

        $label = $model::class.'#'.$model->getKey();

        if ($this->dryRun) {
            $this->line("[dry-run] would update {$label}: ".implode(', ', array_keys($model->getDirty())));

            return;
        }

        $model->save();
        $this->line("Updated {$label}: ".implode(', ', array_keys($model->getDirty())));
    }
}
