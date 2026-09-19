<?php

namespace Modules\Assets\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lunar\Core\Contracts\Actions\Media\DeletesMedia;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductType;
use Modules\Assets\Support\Library\LibraryPathGenerator;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Lunar's galleries, backed by the one library.
 *
 * The media library (Lunar Assets, modules/Assets) is the single source of
 * truth for image FILES. A product's — or a collection's, brand's, product
 * type's, swatch's — gallery image is a LINK: a real Spatie media row on that
 * record, carrying two custom properties that point at the library file.
 *
 * Why a row and not a list of ids: the row is what Lunar reads. The
 * `thumbnail` relation, drag-to-reorder, the primary flag, alt/caption/focal
 * point per gallery, and the variant ↔ media pivot all keep working because
 * they still find media rows. Only the original's LOCATION changes
 * ({@see LibraryPathGenerator}); renditions stay per link, because a brand and
 * a product render the same photo at different sizes.
 *
 * Consequences, each enforced here or in the Spatie seams beside it:
 *  - Removing an image from a gallery removes the link; the file stays in the
 *    library (LibraryFileRemover).
 *  - Replacing a library file updates every gallery showing it (resync()).
 *  - Deleting a library file takes it out of every gallery (unlinkAll()).
 *  - Uploading into a gallery stores into the library first
 *    (AddMediaThroughLibrary) — the same bytes twice reuse one file.
 */
class LibraryLinks
{
    /** Custom property: the library Asset id — stable across file replacements. */
    public const ASSET = 'library_asset_id';

    /** Custom property: the library media row whose original the link shows. */
    public const SOURCE = 'library_media_id';

    /**
     * The records whose image galleries draw from the library, and the library
     * folder their uploads are filed under.
     *
     * A whitelist, not "any HasMedia": a staff avatar or a document attachment
     * is not shop imagery and has no business in the library.
     */
    public const MODELS = [
        Product::class => 'products',
        Collection::class => 'collections',
        Brand::class => 'brands',
        ProductType::class => 'product-types',
        ProductOptionValue::class => 'swatches',
    ];

    public function __construct(
        protected MediaLibraryService $library,
        protected MediaUrl $urls,
        protected ConversionGenerator $conversions,
    ) {}

    public static function isLink(Media $media): bool
    {
        return $media->hasCustomProperty(self::SOURCE);
    }

    public function folderFor(Model $model): ?string
    {
        foreach (self::MODELS as $class => $folder) {
            if ($model instanceof $class) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * Put a library file into a record's gallery.
     *
     * The library file's alt text comes along as the gallery's starting alt;
     * editing it in Lunar's image dialog then changes it for that gallery only.
     *
     * @param  array<string, mixed>  $customProperties
     */
    public function link(Asset $asset, HasMedia&Model $model, ?string $collection = null, array $customProperties = []): Media
    {
        $source = $asset->file;

        if (! $source) {
            throw new InvalidArgumentException("Asset {$asset->id} has no library file to link.");
        }

        /** @var Media $link */
        $link = $model->media()->create([
            'uuid' => (string) Str::uuid(),
            'collection_name' => $collection ?? config('lunar.media.collection', 'images'),
            'name' => $source->name,
            'file_name' => $source->file_name,
            'mime_type' => $source->mime_type,
            'disk' => $source->disk,
            'conversions_disk' => $source->conversions_disk ?: $source->disk,
            'size' => $source->size,
            'manipulations' => [],
            'custom_properties' => [
                ...array_filter(['alt' => $source->getCustomProperty('alt')]),
                ...$customProperties,
                self::ASSET => $asset->id,
                self::SOURCE => $source->id,
            ],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $model->unsetRelation('media');

        // Lunar's own observer may have promoted it to primary; read that back.
        $link->refresh();

        $this->urls->warm($link);

        return $link;
    }

    /**
     * The row in a record's gallery that shows this library file — making one
     * only when there is none.
     *
     * In order: a link to it already in the gallery; an image the gallery
     * still owns with exactly the same bytes (a gallery not yet run through
     * `assets:adopt-galleries` — adopting it later turns that same row into
     * the link, so nothing pointing at it moves); otherwise a new link at the
     * end of the gallery. Choosing a library photo for a colour therefore
     * never puts the same picture into the gallery twice.
     */
    public function mediaInGallery(Asset $asset, HasMedia&Model $model, ?string $collection = null): Media
    {
        $collection ??= config('lunar.media.collection', 'images');
        $gallery = $model->media()->where('collection_name', $collection)->get();

        $linked = $gallery->first(fn (Media $media) => (int) $media->getCustomProperty(self::ASSET) === $asset->id);

        if ($linked) {
            return $linked;
        }

        $source = $asset->file;
        $hash = $source ? $this->library->contentHash($source) : null;

        if ($hash !== null) {
            $owned = $gallery->first(fn (Media $media) => ! self::isLink($media)
                && (int) $media->size === (int) $source->size
                && $this->library->contentHash($media) === $hash);

            if ($owned) {
                return $owned;
            }
        }

        return $this->link($asset, $model, $collection);
    }

    /**
     * Every gallery row pointing at these library files.
     *
     * @param  int|array<int, int>  $assetIds
     * @return Builder<Media>
     */
    public function linksTo(int|array $assetIds): Builder
    {
        return Media::query()->whereIn('custom_properties->'.self::ASSET, (array) $assetIds);
    }

    /**
     * How many gallery images each library file backs, in one query.
     *
     * @param  array<int, int>  $assetIds
     * @return array<int, int>
     */
    public function usageCounts(array $assetIds): array
    {
        if ($assetIds === []) {
            return [];
        }

        return $this->linksTo($assetIds)
            ->pluck('custom_properties')
            ->countBy(fn ($properties) => (int) ($properties[self::ASSET] ?? 0))
            ->all();
    }

    /**
     * Where a library file is shown, for the file manager's details panel.
     *
     * @return array<int, array{type: string, name: string, url: ?string}>
     */
    public function usages(Asset $asset): array
    {
        return $this->linksTo($asset->id)
            ->with('model')
            ->get()
            ->filter(fn (Media $media) => $media->model instanceof Model)
            // One photo twice in a gallery is one place, not two.
            ->unique(fn (Media $media) => $media->model_type.':'.$media->model_id)
            ->map(fn (Media $media) => [
                'type' => $this->folderFor($media->model) ?? 'other',
                'name' => $this->nameOf($media->model),
                'url' => $this->editUrl($media->model),
            ])
            ->values()
            ->all();
    }

    /**
     * Take a library file out of every gallery — the step before deleting it.
     * Goes through Lunar's own action so the primary flag re-points the way it
     * does when staff delete an image by hand.
     */
    public function unlinkAll(Asset $asset): int
    {
        $links = $this->linksTo($asset->id)->get();
        $delete = app(DeletesMedia::class);

        $links->each(fn (Media $link) => $delete->execute($link));

        return $links->count();
    }

    /**
     * Point every gallery row at the library file's current original, after a
     * replacement. Each link's renditions were made from the old picture, so
     * they are discarded and regenerate from the new one.
     */
    public function resync(Asset $asset): int
    {
        $source = $asset->fresh('file')?->file;

        if (! $source) {
            return 0;
        }

        $links = $this->linksTo($asset->id)->get();

        foreach ($links as $link) {
            $this->discardRenditions($link);

            $link->forceFill([
                'file_name' => $source->file_name,
                'mime_type' => $source->mime_type,
                'size' => $source->size,
                'disk' => $source->disk,
                'generated_conversions' => [],
                'responsive_images' => [],
            ]);
            $link->setCustomProperty(self::SOURCE, $source->id);
            // The content hash belongs to the library file, not to a link.
            $link->forgetCustomProperty('sha1');

            // Quietly: Spatie would otherwise "rename" the link's files to the
            // new name — and a link's original is the library's.
            $link->saveQuietly();

            $this->urls->warm($link);
        }

        return $links->count();
    }

    /**
     * Turn a gallery image that owns its file into a link to the library.
     *
     * The media row keeps its id — so its order, primary flag, alt/caption and
     * the variant pivot rows pointing at it all survive — and its original
     * moves into the library, or is dropped when the library already holds the
     * same bytes. Renditions stay where they are: they were made from the same
     * picture.
     *
     * A dry run writes nothing. $planned carries the hashes a dry run has
     * already counted as "moved", so the second gallery holding the same photo
     * is reported as the reuse it will really be.
     *
     * @param  array<string, true>  $planned
     * @return 'moved'|'reused'|'skipped'|'missing'
     */
    public function adopt(Media $media, bool $dryRun = false, array &$planned = []): string
    {
        $model = $media->model;

        if (
            self::isLink($media)
            || ! $model instanceof HasMedia
            || $this->folderFor($model) === null
            || ! $this->library->acceptsMime($media->mime_type)
        ) {
            return 'skipped';
        }

        $disk = Storage::disk($media->disk);
        $original = $media->getPathRelativeToRoot();

        if (! $disk->exists($original)) {
            return 'missing';
        }

        $hash = sha1((string) $disk->get($original));
        $existing = $this->library->findByContent($hash, (int) $media->size, remember: ! $dryRun);

        if ($dryRun) {
            if ($existing || isset($planned[$hash])) {
                return 'reused';
            }

            $planned[$hash] = true;

            return 'moved';
        }

        if ($existing) {
            $this->adoptOnto($media, $existing, $original);

            return 'reused';
        }

        $this->adoptAsNew($media, $model, $original, $hash);

        return 'moved';
    }

    /** The library already has these bytes: link to them, drop our copy. */
    protected function adoptOnto(Media $media, Asset $asset, string $original): void
    {
        $source = $asset->file;
        $renamed = $media->file_name !== $source->file_name;

        $this->becomeLink($media, $asset, $source);

        if ($renamed) {
            // Renditions are named after the file; a different name means
            // they would never be found again. Clear them with the original.
            $this->discardRenditions($media);
        } else {
            Storage::disk($media->disk)->delete($original);
        }
    }

    /** New to the library: move the original in, and link to it. */
    protected function adoptAsNew(Media $media, Model $model, string $original, string $hash): void
    {
        $disk = Storage::disk($media->disk);
        $target = null;

        try {
            DB::transaction(function () use ($media, $model, $original, $hash, $disk, &$target): void {
                $asset = Asset::create([]);

                /** @var Media $source */
                $source = $asset->media()->create([
                    'uuid' => (string) Str::uuid(),
                    'collection_name' => $this->library->collection(),
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'disk' => $media->disk,
                    'conversions_disk' => $media->conversions_disk ?: $media->disk,
                    'size' => $media->size,
                    'manipulations' => [],
                    'custom_properties' => array_filter([
                        'folder' => $this->folderFor($model),
                        'type' => 'image',
                        'sha1' => $hash,
                        'alt' => $media->getCustomProperty('alt'),
                    ]),
                    'generated_conversions' => [],
                    'responsive_images' => [],
                ]);

                $target = $source->getPathRelativeToRoot();

                if (! $disk->move($original, $target)) {
                    $target = null;

                    throw new RuntimeException("Could not move {$original} into the library.");
                }

                $this->becomeLink($media, $asset, $source);
                $this->urls->warm($source);
            });
        } catch (Throwable $e) {
            // The rows rolled back; put the file back where its row expects it.
            if ($target !== null) {
                $disk->move($target, $original);
            }

            throw $e;
        }
    }

    protected function becomeLink(Media $media, Asset $asset, Media $source): void
    {
        $media->forceFill([
            'file_name' => $source->file_name,
            'mime_type' => $source->mime_type,
            'size' => $source->size,
            'disk' => $source->disk,
        ]);
        $media->setCustomProperty(self::ASSET, $asset->id);
        $media->setCustomProperty(self::SOURCE, $source->id);
        // The content hash belongs to the library file, not to a link.
        $media->forgetCustomProperty('sha1');
        $media->saveQuietly();
    }

    protected function discardRenditions(Media $media): void
    {
        foreach (array_unique(array_filter([$media->conversions_disk, $media->disk])) as $disk) {
            Storage::disk($disk)->deleteDirectory(LibraryPathGenerator::ownDirectory($media));
        }

        $this->conversions->forgetAllExists($media);
    }

    protected function nameOf(Model $model): string
    {
        try {
            $name = match (true) {
                $model instanceof Product, $model instanceof Collection => $model->translateAttribute('name'),
                $model instanceof ProductOptionValue => $model->translate('name'),
                default => $model->getAttribute('name'),
            };
        } catch (Throwable) {
            $name = null;
        }

        return is_string($name) && $name !== '' ? $name : '#'.$model->getKey();
    }

    protected function editUrl(Model $model): ?string
    {
        $route = match (true) {
            $model instanceof Product => ['panel.products.edit', $model],
            $model instanceof Collection => ['panel.collections.edit', $model],
            $model instanceof Brand => ['panel.brands.edit', $model],
            $model instanceof ProductType => ['panel.product-types.edit', $model],
            $model instanceof ProductOptionValue => ['panel.settings.product-options.edit', $model->product_option_id],
            default => null,
        };

        try {
            return $route ? route(...$route) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
