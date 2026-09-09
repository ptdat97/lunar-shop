<?php

namespace Modules\Catalog\Support;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductVariant;

/**
 * A product's variant axes, and where each variant sits on them.
 *
 * The storefront picker addresses variants **positionally**: axis 0 value 1,
 * axis 1 value 0 — `[1, 0]`, keyed as `"1-0"`. Both the SSR buttons and
 * `enhance/product-variant.js` are built on that, including the partial-subset
 * index that greys out combinations nobody stocks.
 *
 * That encoding used to be stored: `products.variables` held the axes and each
 * `ProductSku.variants` held its index tuple. Since the purchasable moved to
 * Lunar's `ProductVariant`, the axes are shared `ProductOption`s and a variant
 * points at `ProductOptionValue`s by id — normalised, but no longer positional.
 *
 * So the position is DERIVED here instead of stored. The product's options give
 * the axis order, each option's values give the value order, and a variant's
 * ids are looked up in that table. Same contract, one source of truth, and no
 * client-side change — the JS never learns the ids exist.
 *
 * Memoised per product per request: a 24-card grid otherwise rebuilds the same
 * table once per card.
 *
 * @phpstan-type Axis array{option: ProductOption, values: Collection<int, ProductOptionValue>}
 */
class VariantAxes
{
    /** @var array<int, array<int, mixed>> */
    private array $memo = [];

    /**
     * The product's axes in display order, each with its values in order.
     *
     * Only values some variant of this product actually uses: a shared option
     * carries every colour the shop has ever sold, and offering the shopper a
     * swatch that resolves to nothing is worse than not offering it.
     *
     * @return array<int, Axis>
     */
    public function axes(Product $product): array
    {
        return $this->build($product)['axes'];
    }

    /**
     * A variant's positional index tuple, e.g. `[1, 0]`.
     *
     * @return array<int, int>
     */
    public function indexes(Product $product, ProductVariant $variant): array
    {
        $lookup = $this->build($product)['lookup'];
        $indexes = [];

        foreach ($variant->values as $value) {
            $position = $lookup[$value->id] ?? null;

            if ($position !== null) {
                [$axis, $index] = $position;
                $indexes[$axis] = $index;
            }
        }

        ksort($indexes);

        return array_values($indexes);
    }

    /**
     * The option NAME → VALUE pairs a variant represents, localised, in axis
     * order — `[['option' => 'Color', 'value' => 'Black'], …]`.
     *
     * @return array<int, array{option: string, value: string}>
     */
    public function pairs(Product $product, ProductVariant $variant): array
    {
        $lookup = $this->build($product)['lookup'];
        $pairs = [];

        foreach ($variant->values as $value) {
            $position = $lookup[$value->id] ?? null;

            if ($position === null) {
                continue;
            }

            [$axis] = $position;
            $option = $this->build($product)['axes'][$axis]['option'];

            $pairs[$axis] = [
                'option' => (string) $option->translate('name'),
                'value' => (string) $value->translate('name'),
            ];
        }

        ksort($pairs);

        return array_values($pairs);
    }

    /**
     * The stable key the picker jumps by. Kept identical to the old positional
     * format so the rendered payload and the JS agree without either changing.
     *
     * @param  array<int, int|string>  $indexes
     */
    public static function key(array $indexes): string
    {
        return collect($indexes)->map(fn ($index) => (string) (int) $index)->implode('-');
    }

    /**
     * @return array{axes: array<int, Axis>, lookup: array<int, array{0: int, 1: int}>}
     */
    private function build(Product $product): array
    {
        if (isset($this->memo[$product->id])) {
            return $this->memo[$product->id];
        }

        $usedValueIds = $product->variants
            ->flatMap(fn (ProductVariant $variant) => $variant->values->pluck('id'))
            ->unique()
            ->flip();

        $axes = [];
        $lookup = [];

        // `productOptions()` already orders by the pivot position.
        $options = $product->productOptions()
            ->with(['values' => fn ($query) => $query->orderBy('position')])
            ->get();

        foreach ($options as $axis => $option) {
            $values = $option->values
                ->filter(fn (ProductOptionValue $value) => $usedValueIds->has($value->id))
                ->values();

            if ($values->isEmpty()) {
                continue;
            }

            foreach ($values as $index => $value) {
                $lookup[$value->id] = [count($axes), $index];
            }

            $axes[] = ['option' => $option, 'values' => $values];
        }

        return $this->memo[$product->id] = ['axes' => $axes, 'lookup' => $lookup];
    }
}
