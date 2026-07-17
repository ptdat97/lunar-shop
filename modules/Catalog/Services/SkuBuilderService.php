<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;

/**
 * Persists a product's flexible variant definition (VaniCommerce model):
 *
 *   variables  — free-form JSON variant definitions on the product.
 *   skus[]     — one row per Cartesian combination, each with its own
 *                price / quantity / images / sku / status.
 *
 * Save strategy is delete-and-recreate (mirrors VaniCommerce ProductService):
 * the posted `skus` array is authoritative, so the stored set is always
 * internally consistent with `variables`. Because ids are therefore not stable
 * across edits, purchasables are identified downstream by the `sku` string, and
 * the admin builder preserves per-SKU data across a rebuild by MATCHING on the
 * `variants` index combination (see saveFromBuilder's carry-over), not the id.
 *
 * The authoritative price the Lunar Pricing engine reads lives in
 * `lunar_prices` (priceable_type = product_sku). This service syncs each SKU's
 * `price` column down to a base Price row so the engine and the admin cache
 * never drift.
 */
class SkuBuilderService
{
    /**
     * Generate every Cartesian combination of the given variables' value
     * indexes. A variable with values [Black, White] and another [S, M] yields
     * [[0,0],[0,1],[1,0],[1,1]] — one entry per resulting SKU.
     *
     * @param  array<int, array{values?: array}>  $variables
     * @return array<int, array<int, int>>
     */
    public function combinations(array $variables): array
    {
        $axes = collect($variables)
            ->map(fn ($variable) => array_keys($variable['values'] ?? []))
            ->reject(fn ($indexes) => empty($indexes))
            ->values()
            ->all();

        if (empty($axes)) {
            return [];
        }

        $result = [[]];

        foreach ($axes as $indexes) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($indexes as $index) {
                    $next[] = [...$prefix, $index];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * Persist the product's variables + SKU rows.
     *
     * @param  array<int, mixed>  $variables  the variant definitions
     * @param  array<int, array<string, mixed>>  $skus  one row per combination
     *
     * @throws ValidationException when a SKU code collides with another product
     */
    public function save(Product $product, array $variables, array $skus): void
    {
        $this->assertUniqueSkuCodes($product, $skus);

        DB::transaction(function () use ($product, $variables, $skus) {
            $product->variables = array_values($variables);
            $product->save();

            // Authoritative rewrite: drop the old set, recreate from the payload.
            $product->skus()->forceDelete();

            $currency = Currency::getDefault();

            foreach (array_values($skus) as $position => $row) {
                $sku = $product->skus()->create([
                    'variants' => $row['variants'] ?? [],
                    'position' => $position,
                    'images' => $row['images'] ?? [],
                    'model' => $row['model'] ?? '',
                    'sku' => $row['sku'],
                    'price' => (int) ($row['price'] ?? 0),
                    'origin_price' => (int) ($row['origin_price'] ?? 0),
                    'cost_price' => isset($row['cost_price']) ? (int) $row['cost_price'] : null,
                    'quantity' => (int) ($row['quantity'] ?? 0),
                    'weight' => $row['weight'] ?? null,
                    'tax_class_id' => $row['tax_class_id'] ?? null,
                    'is_default' => (bool) ($row['is_default'] ?? $position === 0),
                    'status' => $row['status'] ?? 'published',
                ]);

                $this->syncBasePrice($sku, $currency);
            }

            // Lunar requires at least one purchasable; guarantee exactly one
            // default even if the payload forgot to flag one.
            if ($product->skus()->where('is_default', true)->doesntExist()) {
                $product->skus()->orderBy('position')->limit(1)->update(['is_default' => true]);
            }
        });
    }

    /**
     * Mirror a SKU's `price` cache down to its authoritative base Price row
     * (min_quantity 1, default currency, no customer group). `compare_price`
     * carries the strike-through `origin_price` when it is higher.
     */
    protected function syncBasePrice(ProductSku $sku, ?Currency $currency): void
    {
        if (! $currency) {
            return;
        }

        $sku->prices()->updateOrCreate(
            [
                'currency_id' => $currency->id,
                'customer_group_id' => null,
                'min_quantity' => 1,
            ],
            [
                'price' => max(0, (int) $sku->price),
                'compare_price' => $sku->origin_price > $sku->price ? (int) $sku->origin_price : null,
            ],
        );
    }

    /**
     * A SKU code must be unique across the whole catalogue. Reject a payload
     * that reuses a code owned by a DIFFERENT product (VaniCommerce guard).
     *
     * @param  array<int, array<string, mixed>>  $skus
     *
     * @throws ValidationException
     */
    protected function assertUniqueSkuCodes(Product $product, array $skus): void
    {
        $codes = collect($skus)->pluck('sku')->filter();

        // Duplicate within the same payload.
        $dupInPayload = $codes->duplicates();
        if ($dupInPayload->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skus' => 'Duplicate SKU code in this product: '.$dupInPayload->implode(', '),
            ]);
        }

        $clashing = ProductSku::query()
            ->withTrashed()
            ->whereIn('sku', $codes->all())
            ->where('product_id', '!=', $product->id)
            ->pluck('sku');

        if ($clashing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skus' => 'These SKU codes are already used by another product: '.$clashing->unique()->implode(', '),
            ]);
        }
    }

    /**
     * Carry per-SKU commercial data (price, quantity, images, status, code)
     * forward when the admin rebuilds the matrix, so adding/removing an option
     * value does NOT wipe the SKUs that still exist — matched by their
     * `variants` index combination, which is stable while ids are not.
     *
     * @param  array<int, array<int, int>>  $combinations  fresh index combos
     * @return Collection<int, array<string, mixed>> builder rows
     */
    public function carryOver(Product $product, array $combinations): Collection
    {
        $existing = $product->skus()
            ->get()
            ->keyBy(fn (ProductSku $sku) => $this->comboKey($sku->variants ?? []));

        return collect($combinations)->map(function (array $combo, int $i) use ($existing) {
            $prior = $existing->get($this->comboKey($combo));

            return [
                'variants' => $combo,
                'position' => $i,
                'sku' => $prior->sku ?? '',
                'model' => $prior->model ?? '',
                'price' => $prior->price ?? 0,
                'origin_price' => $prior->origin_price ?? 0,
                'cost_price' => $prior->cost_price ?? null,
                'quantity' => $prior->quantity ?? 0,
                'weight' => $prior->weight ?? null,
                'images' => $prior->images ?? [],
                'is_default' => $prior->is_default ?? ($i === 0),
                'status' => $prior->status ?? 'published',
            ];
        });
    }

    /**
     * Stable key for a combination of value indexes, e.g. [0,1] => "0-1".
     *
     * @param  array<int, int>  $combo
     */
    protected function comboKey(array $combo): string
    {
        return implode('-', $combo);
    }
}
