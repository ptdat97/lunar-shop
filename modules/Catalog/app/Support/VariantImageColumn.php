<?php

namespace Modules\Catalog\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Modules\Assets\Services\LibraryLinks;
use Modules\Catalog\Services\VariantImages;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Moves the shop's old `image_asset_ids` column into Lunar's variant images.
 *
 * The column predates Lunar 2.0's `media_product_variant` pivot and was the
 * storefront's per-colour gallery. It is replaced by the pivot (VariantImages)
 * and dropped by the migration that calls this.
 *
 * Its ids are read two ways, because two writers filled it differently:
 *  - an id that is a media row of the variant's own product gallery is taken
 *    as exactly that — what the demo seeder wrote, and what the pivot holds;
 *  - any other id is taken as a library Asset (what the pre-2.0 picker wrote)
 *    and brought into the product's gallery through LibraryLinks, which reuses
 *    a link or an identical image already there instead of duplicating it.
 * Ids that are neither are dropped: they resolved to nothing on the storefront
 * either.
 *
 * The storefront read the column as Asset ids only, so seeded sets showed the
 * wrong photos (library files that happened to share the id) or none at all.
 * Reading media ids as media ids here is also that fix.
 */
class VariantImageColumn
{
    public const COLUMN = 'image_asset_ids';

    public function __construct(
        protected VariantImages $images,
        protected LibraryLinks $links,
    ) {}

    /**
     * Read the column and write every non-empty set to the pivot.
     *
     * @return array{variants: int, images: int, dropped: int}
     */
    public function moveToLunar(): array
    {
        $totals = ['variants' => 0, 'images' => 0, 'dropped' => 0];

        DB::table((new ProductVariant)->getTable())
            ->whereNotNull(self::COLUMN)
            ->select(['id', self::COLUMN])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$totals): void {
                $sets = [];

                foreach ($rows as $row) {
                    $sets[$row->id] = json_decode((string) $row->{self::COLUMN}, true) ?: [];
                }

                foreach ($this->import($sets) as $key => $count) {
                    $totals[$key] += $count;
                }
            });

        return $totals;
    }

    /**
     * Write id sets to Lunar's pivot, one variant at a time.
     *
     * @param  iterable<int, array<int, mixed>>  $idsByVariant  variant id => ids as the column stored them
     * @return array{variants: int, images: int, dropped: int}
     */
    public function import(iterable $idsByVariant): array
    {
        $totals = ['variants' => 0, 'images' => 0, 'dropped' => 0];

        foreach ($idsByVariant as $variantId => $raw) {
            $ids = collect($raw)
                // An early build stored `{id: …}` objects rather than bare ids.
                ->map(fn ($id) => is_array($id) ? ($id['id'] ?? null) : $id)
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            $variant = ProductVariant::with('product')->find($variantId);

            if ($ids->isEmpty() || ! $variant?->product) {
                continue;
            }

            $media = $this->resolve($variant->product, $ids);

            $this->images->syncVariants([$variant], $media);

            $totals['variants']++;
            $totals['images'] += $media->count();
            $totals['dropped'] += $ids->unique()->count() - $media->count();
        }

        return $totals;
    }

    /**
     * Put the sets back into the column, for rolling the migration back.
     *
     * Only links carry a library Asset id. An image the gallery still owns has
     * none, so it is left out — the one thing a rollback cannot restore.
     */
    public function restoreFromLunar(): void
    {
        ProductVariant::query()->with('images')->chunkById(200, function ($variants): void {
            foreach ($variants as $variant) {
                $ids = $variant->images
                    ->map(fn (Media $media) => $media->getCustomProperty(LibraryLinks::ASSET))
                    ->filter()
                    ->values()
                    ->all();

                DB::table($variant->getTable())
                    ->where('id', $variant->id)
                    ->update([self::COLUMN => $ids === [] ? null : json_encode($ids)]);
            }
        });
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Media>
     */
    protected function resolve(Product $product, Collection $ids): Collection
    {
        $gallery = $product->media()
            ->where('collection_name', config('lunar.media.collection', 'images'))
            ->get()
            ->keyBy('id');

        return $ids
            ->map(function (int $id) use ($product, $gallery): ?Media {
                if ($gallery->has($id)) {
                    return $gallery->get($id);
                }

                $asset = Asset::with('file')->find($id);

                return $asset?->file ? $this->links->mediaInGallery($asset, $product) : null;
            })
            ->filter()
            ->unique('id')
            ->values();
    }
}
