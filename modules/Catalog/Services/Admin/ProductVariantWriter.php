<?php

namespace Modules\Catalog\Services\Admin;

use Lunar\Facades\DB;
use Lunar\Models\Contracts\ProductOption as ProductOptionContract;
use Lunar\Models\Contracts\ProductVariant as ProductVariantContract;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

/**
 * Persists the variant matrix built in SimpleVariantsWidget: creates/updates
 * variants for each permutation, syncs their option values and product options,
 * prunes variants no longer in the matrix — all in one transaction.
 *
 * The widget owns the Livewire UI state and option persistence (it must mutate
 * $configuredOptions to back-fill new ids first); this service owns the
 * DB-writing business logic so it stays out of the Filament layer (§4/§17).
 */
class ProductVariantWriter
{
    /**
     * Write the permutation matrix. Expects options already persisted (each
     * $configuredOptions row has an 'id'). Returns the variants array with each
     * row's 'variant_id' back-filled so the widget can reflect new ids.
     *
     * @param  array<int, array<string, mixed>>  $variants  permutation rows
     * @param  array<int, array<string, mixed>>  $configuredOptions
     * @return array<int, array<string, mixed>>
     */
    public function save(Product $product, array $variants, array $configuredOptions): array
    {
        return DB::transaction(function () use ($product, $variants, $configuredOptions) {
            if (! count($variants)) {
                $this->clearVariants($product);

                return $variants;
            }

            $variants = $this->writeVariants($product, $variants);

            $product->productOptions()->sync(
                collect($configuredOptions)->mapWithKeys(fn ($option) => [
                    $option['id'] => ['position' => $option['position']],
                ])->all()
            );

            $keptIds = collect($variants)->pluck('variant_id');

            $product->variants()->whereNotIn('id', $keptIds)
                ->get()
                ->each(fn ($variant) => $variant->delete());

            return $variants;
        });
    }

    /**
     * All permutations were removed: detach the options and drop every variant
     * except the first, which stays as the product's single default variant.
     */
    protected function clearVariants(Product $product): void
    {
        $variant = $product->variants()->first();
        $variant?->values()->detach();

        $product->productOptions()->exclusive()->each(
            fn (ProductOptionContract $productOption) => $productOption->delete()
        );

        $product->productOptions()->shared()->detach();

        if ($variant) {
            $product->variants()
                ->where('id', '!=', $variant->id)
                ->get()
                ->each(fn (ProductVariantContract $other) => $other->delete());
        }
    }

    /**
     * Upsert one variant per permutation row (reusing an existing variant,
     * replicating a copied one, or creating fresh), set its base price and
     * option values, and back-fill 'variant_id' into the returned rows.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return array<int, array<string, mixed>>
     */
    protected function writeVariants(Product $product, array $variants): array
    {
        $currency = Currency::getDefault();

        foreach ($variants as $index => $row) {
            [$variant, $basePrice] = $this->resolveVariant($product, $row);

            $variant->sku = $row['sku'];
            $variant->stock = (int) $row['stock'];
            $variant->model = trim((string) ($row['model'] ?? '')) ?: null;
            $variant->status = ($row['status'] ?? 'published') === 'disabled' ? 'disabled' : 'published';
            $variant->cost_price = $this->toMinor($row['cost_price'] ?? '', $currency);

            $weight = trim((string) ($row['weight'] ?? ''));
            if ($weight !== '') {
                // The builder's weight column is in kilograms; force the unit
                // (Lunar's dimensions() migration defaults every *_unit to 'mm',
                // which is meaningless for weight).
                $variant->weight_value = (float) $weight;
                $variant->weight_unit = 'kg';
            }

            $variant->save();

            $basePrice ??= $variant->prices()->make([
                'min_quantity' => 1,
                'currency_id' => $currency->id,
            ]);
            $basePrice->price = $this->toMinor($row['price'], $basePrice->currency);
            $basePrice->compare_price = $this->toMinor($row['compare_price'] ?? '', $basePrice->currency);
            $basePrice->save();

            $variant->values()->sync($row['value_ids']);
            $this->syncImages($variant, $row);

            $variants[$index]['variant_id'] = $variant->id;
        }

        return $variants;
    }

    /**
     * Convert a decimal amount string to minor units for the given currency,
     * or null for a blank input. Gathers the ×factor conversion used for price,
     * compare_price and cost_price.
     */
    protected function toMinor(mixed $decimal, Currency $currency): ?int
    {
        $decimal = trim((string) $decimal);

        if ($decimal === '') {
            return null;
        }

        return (int) bcmul((string) ((float) $decimal), (string) $currency->factor);
    }

    /**
     * Sync a variant's gallery images (pivot media_product_variant) from the
     * media ids the builder assigned, marking the chosen primary. Only runs when
     * the row carries image_ids so untouched rows keep their pivot. sync() prunes
     * ids no longer selected — scoped to this variant's pivot, never product media.
     *
     * @param  array<string, mixed>  $row
     */
    protected function syncImages(ProductVariant $variant, array $row): void
    {
        if (! array_key_exists('image_ids', $row)) {
            return;
        }

        $primary = $row['primary_image_id'] ?? null;

        $sync = [];
        foreach (array_values((array) $row['image_ids']) as $position => $mediaId) {
            $sync[(int) $mediaId] = [
                'primary' => (int) $mediaId === (int) $primary,
                'position' => $position + 1,
            ];
        }

        $variant->images()->sync($sync);
    }

    /**
     * Return [variant, basePrice] for a permutation row: an existing variant,
     * a replicated copy of another, or a brand-new one with a tax class.
     *
     * @param  array<string, mixed>  $row
     * @return array{0: ProductVariant, 1: ?Price}
     */
    protected function resolveVariant(Product $product, array $row): array
    {
        if (! empty($row['variant_id'])) {
            $variant = ProductVariant::find($row['variant_id']);

            return [$variant, $variant->basePrices->first()];
        }

        if (! empty($row['copied_id'])) {
            $copied = ProductVariant::find($row['copied_id']);
            $variant = $copied->replicate();
            $variant->save();

            $copiedPrice = $copied->basePrices->first();
            $basePrice = null;

            if ($copiedPrice) {
                $basePrice = $copiedPrice->replicate();
                $basePrice->priceable_id = $variant->id;
            }

            return [$variant, $basePrice];
        }

        return [
            new ProductVariant([
                'product_id' => $product->id,
                'tax_class_id' => TaxClass::getDefault()->id,
            ]),
            null,
        ];
    }
}
