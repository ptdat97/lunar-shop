<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;
use Modules\Assets\Services\LibraryLinks;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Photos per colour — stored where Lunar keeps variant images.
 *
 * Lunar 2.0 gives every variant an ordered selection of its product's gallery
 * (`ProductVariant::images()`, the `media_product_variant` pivot, first image
 * primary), with its own per-variant picker on the variant screen and
 * `getThumbnail()` reading it. That is the one place variant photos live; the
 * storefront reads it too (ProductVariantResource).
 *
 * What Lunar does not have is the shape a fashion shop thinks in: photos
 * belong to a COLOUR, and every size of that colour shows the same set. Setting
 * that one variant at a time is twelve identical edits for a 3-colour, 4-size
 * shirt. This service is that shape over Lunar's data — it writes the same
 * pivot rows Lunar's own picker writes, so either editor sees the other's work.
 */
class VariantImages
{
    public function __construct(protected LibraryLinks $links) {}

    /**
     * The option a product's photos vary by: its first colour or swatch
     * option, or — when it has exactly one option — that one. Null when there
     * is no such option (a size-only product has no per-colour photos).
     */
    public function axis(Product $product): ?ProductOption
    {
        $options = $product->productOptions()->get();

        $visual = $options->first(fn (ProductOption $option) => in_array(
            ProductOptionType::tryFrom((string) $option->type),
            [ProductOptionType::Colour, ProductOptionType::Swatch],
            true,
        ));

        return $visual ?? ($options->count() === 1 ? $options->first() : null);
    }

    /**
     * One group per value of the axis that some variant of the product uses,
     * in the option's own value order.
     *
     * `media` is the set the group's variants show — read from the first of
     * them. `mixed` says the variants of this colour disagree (someone set one
     * size by hand on the variant screen): saving the group makes them agree.
     *
     * @return array<int, array{value: ProductOptionValue, variants: Collection<int, ProductVariant>, media: Collection<int, Media>, mixed: bool}>
     */
    public function groups(Product $product, ?ProductOption $axis = null): array
    {
        $axis ??= $this->axis($product);

        if (! $axis) {
            return [];
        }

        $groups = [];

        foreach ($product->variants()->with(['values', 'images'])->orderBy('id')->get() as $variant) {
            $value = $variant->values->firstWhere('product_option_id', $axis->id);

            if ($value) {
                $groups[$value->id] ??= ['value' => $value, 'variants' => collect()];
                $groups[$value->id]['variants']->push($variant);
            }
        }

        return collect($groups)
            ->sortBy(fn (array $group) => [$group['value']->position, $group['value']->id])
            ->map(function (array $group): array {
                $sets = $group['variants']->map(fn (ProductVariant $variant) => $variant->images->pluck('id')->implode(','));

                return [
                    ...$group,
                    'media' => $group['variants']->first()->images->values(),
                    'mixed' => $sets->unique()->count() > 1,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Give every variant with this value the same photos, in this order; the
     * first becomes each variant's primary. An empty list clears them, and the
     * storefront falls back to the product's gallery.
     *
     * Items name a gallery image (`media_id`) or a library file (`asset_id`).
     * A library file is brought into the gallery through LibraryLinks — as a
     * link, or as the gallery's existing copy of the same bytes — so choosing a
     * photo never duplicates it.
     *
     * @param  array<int, array{media_id?: int|string, asset_id?: int|string}>  $items
     * @return Collection<int, Media> the gallery rows now assigned
     */
    public function assign(Product $product, ProductOptionValue $value, array $items): Collection
    {
        return DB::transaction(function () use ($product, $value, $items): Collection {
            $gallery = $product->media()
                ->where('collection_name', config('lunar.media.collection', 'images'))
                ->get()
                ->keyBy('id');

            $media = collect();

            foreach ($items as $item) {
                if (isset($item['media_id'])) {
                    $row = $gallery->get((int) $item['media_id'])
                        ?? throw new InvalidArgumentException("Media {$item['media_id']} is not in this product's gallery.");
                } else {
                    $asset = Asset::with('file')->find($item['asset_id'] ?? null);

                    if (! $asset?->file) {
                        throw new InvalidArgumentException('Library file '.($item['asset_id'] ?? '?').' does not exist.');
                    }

                    $row = $this->links->mediaInGallery($asset, $product);
                }

                if (! $media->contains('id', $row->id)) {
                    $media->push($row);
                }
            }

            $this->syncVariants($this->variantsWith($product, $value), $media);

            return $media->values();
        });
    }

    /**
     * Write one ordered image set to Lunar's pivot for these variants — the
     * same shape Lunar's own ProductVariantMediaController writes.
     *
     * @param  iterable<int, ProductVariant>  $variants
     * @param  Collection<int, Media>  $media
     */
    public function syncVariants(iterable $variants, Collection $media): void
    {
        $sync = $media->values()
            ->mapWithKeys(fn (Media $row, int $index) => [$row->id => [
                'primary' => $index === 0,
                'position' => $index + 1,
            ]])
            ->all();

        foreach ($variants as $variant) {
            $variant->images()->sync($sync);
        }
    }

    /** @return Collection<int, ProductVariant> */
    public function variantsWith(Product $product, ProductOptionValue $value): Collection
    {
        return $product->variants()
            ->whereHas('values', fn ($query) => $query->whereKey($value->id))
            ->get();
    }
}
