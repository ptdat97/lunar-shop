<?php

namespace Modules\Catalog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Modules\Catalog\Models\ProductSku;

/**
 * One-off, idempotent port of the legacy Lunar ProductVariant graph into the
 * flexible SKU model:
 *
 *   - Each product's distinct option/value pairs become `products.variables`
 *     (a variable per option, a value per option-value, in first-seen order).
 *   - Each ProductVariant becomes a ProductSku whose `variants` array indexes
 *     into that blob, copying sku/model/cost_price/status/quantity/images.
 *   - Each variant's `lunar_prices` rows are re-pointed at the new SKU
 *     (priceable_type product_variant -> product_sku) so pricing is preserved.
 *
 * Re-runnable: a product that already has SKUs is skipped unless --force.
 */
class MigrateVariantsToSkus extends Command
{
    protected $signature = 'catalog:migrate-variants-to-skus {--force : Rebuild SKUs for products that already have them}';

    protected $description = 'Port legacy ProductVariant data into the flexible product_skus model';

    public function handle(): int
    {
        $products = Product::query()->withTrashed()->with([
            'variants' => fn ($q) => $q->withTrashed()->with('values.option'),
        ])->get();

        $this->info("Porting {$products->count()} product(s)…");
        $migrated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            if ($product->skus()->exists() && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($product) {
                if ($this->option('force')) {
                    // Reverse-map any refs currently on this product's SKUs back
                    // onto the matching variant BEFORE deleting the SKUs, so the
                    // repoint pass below can move them onto the freshly-created
                    // SKUs. Without this, a second --force run would orphan every
                    // price/line (they'd point at a since-deleted SKU id).
                    $this->rewindSkuRefsToVariants($product);
                    $product->skus()->forceDelete();
                }

                [$variables, $optionOrder, $valueOrder] = $this->buildVariables($product);

                $product->variables = $variables;
                $product->save();

                foreach ($product->variants as $position => $variant) {
                    $combo = $this->comboFor($variant, $optionOrder, $valueOrder);

                    $sku = ProductSku::create([
                        'product_id' => $product->id,
                        'tax_class_id' => $variant->tax_class_id,
                        'variants' => $combo,
                        'position' => $position,
                        'images' => $variant->images->pluck('id')->all(),
                        'model' => $variant->model ?? '',
                        'sku' => $variant->sku ?: ('SKU-'.$product->id.'-'.$variant->id),
                        'cost_price' => $variant->cost_price,
                        'quantity' => (int) $variant->stock,
                        'is_default' => $position === 0,
                        'status' => $variant->status ?? 'published',
                    ]);

                    $this->repointPrices($variant, $sku);
                    $this->repointDiscountables($variant, $sku);
                    $this->repointLines($variant, $sku);
                }

                // Cache the base price back onto the SKU column for the admin UI.
                foreach ($product->skus()->with('prices')->get() as $sku) {
                    $base = $sku->prices
                        ->where('min_quantity', 1)
                        ->whereNull('customer_group_id')
                        ->first();
                    if ($base) {
                        $sku->update([
                            'price' => (int) $base->price->value,
                            'origin_price' => (int) ($base->compare_price?->value ?? 0),
                        ]);
                    }
                }
            });

            $migrated++;
        }

        $this->info("Done. Migrated {$migrated}, skipped {$skipped} (already had SKUs).");

        return self::SUCCESS;
    }

    /**
     * Build the product's `variables` blob from its variants' option values,
     * plus lookup maps (option id -> variable index, "optId:valId" -> value
     * index) used to translate each variant into an index combination.
     *
     * @return array{0: array, 1: array<int, int>, 2: array<string, int>}
     */
    protected function buildVariables(Product $product): array
    {
        $variables = [];
        $optionOrder = [];   // option_id => variable index
        $valueOrder = [];    // "optionId:valueId" => value index

        foreach ($product->variants as $variant) {
            foreach ($variant->values as $value) {
                $option = $value->option;
                if (! $option) {
                    continue;
                }

                if (! isset($optionOrder[$option->id])) {
                    $optionOrder[$option->id] = count($variables);
                    $variables[$optionOrder[$option->id]] = [
                        'name' => $this->translations($option->name),
                        'values' => [],
                        'isImage' => false,
                    ];
                }

                $vi = $optionOrder[$option->id];
                $key = $option->id.':'.$value->id;

                if (! isset($valueOrder[$key])) {
                    $valueOrder[$key] = count($variables[$vi]['values']);
                    $variables[$vi]['values'][] = [
                        'name' => $this->translations($value->name),
                        'image' => '',
                    ];
                }
            }
        }

        return [$variables, $optionOrder, $valueOrder];
    }

    /**
     * The value-index combination for one variant, aligned to variable order.
     *
     * @param  array<int, int>  $optionOrder
     * @param  array<string, int>  $valueOrder
     * @return array<int, int>
     */
    protected function comboFor(ProductVariant $variant, array $optionOrder, array $valueOrder): array
    {
        $combo = array_fill(0, count($optionOrder), 0);

        foreach ($variant->values as $value) {
            $optionId = $value->option?->id;
            if ($optionId === null || ! isset($optionOrder[$optionId])) {
                continue;
            }
            $combo[$optionOrder[$optionId]] = $valueOrder[$optionId.':'.$value->id] ?? 0;
        }

        return $combo;
    }

    /**
     * Re-point a variant's prices at the new SKU (idempotent by morph target).
     */
    protected function repointPrices(ProductVariant $variant, ProductSku $sku): void
    {
        Price::query()
            ->where('priceable_type', $variant->getMorphClass())
            ->where('priceable_id', $variant->id)
            ->update([
                'priceable_type' => $sku->getMorphClass(),
                'priceable_id' => $sku->id,
            ]);
    }

    /**
     * Undo a prior port for one product: move every price / discountable / line
     * that points at this product's SKUs back onto the matching variant (matched
     * by the shared `sku` code), so a --force rebuild can re-point them onto the
     * new SKUs instead of orphaning them.
     */
    protected function rewindSkuRefsToVariants(Product $product): void
    {
        $skuMorph = (new ProductSku)->getMorphClass();
        $variantMorph = (new ProductVariant)->getMorphClass();

        $variantBySku = ProductVariant::withTrashed()
            ->where('product_id', $product->id)
            ->get()
            ->keyBy('sku');

        foreach ($product->skus()->withTrashed()->get() as $sku) {
            $variant = $variantBySku->get($sku->sku);
            if (! $variant) {
                continue;
            }

            Price::where('priceable_type', $skuMorph)->where('priceable_id', $sku->id)
                ->update(['priceable_type' => $variantMorph, 'priceable_id' => $variant->id]);

            DB::table('lunar_discountables')
                ->where('discountable_type', $skuMorph)->where('discountable_id', $sku->id)
                ->update(['discountable_type' => $variantMorph, 'discountable_id' => $variant->id]);

            foreach (['lunar_order_lines', 'lunar_cart_lines'] as $table) {
                DB::table($table)
                    ->where('purchasable_type', $skuMorph)->where('purchasable_id', $sku->id)
                    ->update(['purchasable_type' => $variantMorph, 'purchasable_id' => $variant->id]);
            }
        }
    }

    /**
     * Re-point historical cart + order lines from the old variant purchasable to
     * the new SKU, so co-purchase, fit-history and cart pruning read consistent
     * `product_sku` rows. Order lines also carry an immutable price snapshot, so
     * only the morph target changes — historical totals are untouched.
     */
    protected function repointLines(ProductVariant $variant, ProductSku $sku): void
    {
        foreach (['lunar_order_lines', 'lunar_cart_lines'] as $table) {
            DB::table($table)
                ->where('purchasable_type', $variant->getMorphClass())
                ->where('purchasable_id', $variant->id)
                ->update([
                    'purchasable_type' => $sku->getMorphClass(),
                    'purchasable_id' => $sku->id,
                ]);
        }
    }

    /**
     * Re-point any discount limitation/condition/exclusion rows that targeted
     * this variant at the new SKU, so variant-scoped promotions keep applying.
     */
    protected function repointDiscountables(ProductVariant $variant, ProductSku $sku): void
    {
        DB::table('lunar_discountables')
            ->where('discountable_type', $variant->getMorphClass())
            ->where('discountable_id', $variant->id)
            ->update([
                'discountable_type' => $sku->getMorphClass(),
                'discountable_id' => $sku->id,
            ]);
    }

    /**
     * Normalise a Lunar translated attribute (array/collection/string) into a
     * plain {locale: string} map for the `variables` blob.
     *
     * @return array<string, string>
     */
    protected function translations(mixed $value): array
    {
        $locale = app()->getLocale();

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Collection) {
            return $value->all();
        }

        // Lunar casts translated attributes to an ArrayObject / Arrayable.
        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return [$locale => (string) $value];
    }
}
