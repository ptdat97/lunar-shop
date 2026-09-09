<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Core\Contracts\Actions\Products\AdjustsStock;
use Lunar\Core\Models\Collection;
use Lunar\Core\Models\CollectionGroup;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\Url;

/**
 * Minimal demo data to exercise the Phase 1 API end-to-end.
 * Idempotent: safe to run repeatedly.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::getDefault();
        $type = ProductType::first() ?? ProductType::create(['name' => 'Default']);

        $samples = [
            ['name' => 'Classic Tee', 'slug' => 'classic-tee', 'sku' => 'TEE-001', 'price' => 19900],
            ['name' => 'Denim Jacket', 'slug' => 'denim-jacket', 'sku' => 'JKT-001', 'price' => 89900],
            ['name' => 'Summer Dress', 'slug' => 'summer-dress', 'sku' => 'DRS-001', 'price' => 49900],
        ];

        $products = [];

        foreach ($samples as $s) {
            $product = Product::where('status', 'published')
                ->whereHas('urls', fn ($q) => $q->where('slug', $s['slug']))
                ->first();

            if (! $product) {
                $product = Product::create([
                    'product_type_id' => $type->id,
                    'status' => 'published',
                    'brand_id' => null,
                    'name' => ['en' => $s['name']],
                ]);

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $s['sku'],
                    'unit_quantity' => 1,
                    'tax_class_id' => TaxClass::getDefault()?->id,
                ]);

                // Lunar 2.0 has no `stock` column: on-hand lives in a per-location
                // `StockLevel` fed by the movement ledger. `AdjustStock` records the
                // opening movement and recomputes the rollup, which is what a real
                // stock-in does — so demo data takes the same path as production.
                app(AdjustsStock::class)->execute($variant, 50, 'seed');

                Price::create([
                    'price' => $s['price'],
                    'currency_id' => $currency->id,
                    'priceable_type' => $variant->getMorphClass(),
                    'priceable_id' => $variant->id,
                ]);

                Url::create([
                    'slug' => $s['slug'],
                    'element_type' => $product->getMorphClass(),
                    'element_id' => $product->id,
                    'default' => true,
                    'language_id' => Language::getDefault()->id,
                ]);
            }

            $products[] = $product;
        }

        // A collection with a URL, containing the products.
        $group = CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Main', 'handle' => 'main']);

        $collection = Collection::whereHas('urls', fn ($q) => $q->where('slug', 'new-arrivals'))->first();

        if (! $collection) {
            $collection = Collection::create([
                'collection_group_id' => $group->id,
                'name' => ['en' => 'New Arrivals'],
            ]);

            Url::create([
                'slug' => 'new-arrivals',
                'element_type' => $collection->getMorphClass(),
                'element_id' => $collection->id,
                'default' => true,
                'language_id' => Language::getDefault()->id,
            ]);
        }

        $collection->products()->syncWithoutDetaching(collect($products)->pluck('id'));
    }
}
