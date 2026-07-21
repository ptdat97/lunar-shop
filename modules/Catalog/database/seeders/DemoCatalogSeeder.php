<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\Url;

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
                    'attribute_data' => ['name' => new Text($s['name'])],
                ]);

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $s['sku'],
                    'stock' => 50,
                    'unit_quantity' => 1,
                    'tax_class_id' => TaxClass::getDefault()?->id,
                ]);

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

        $collection = LunarCollection::whereHas('urls', fn ($q) => $q->where('slug', 'new-arrivals'))->first();

        if (! $collection) {
            $collection = LunarCollection::create([
                'collection_group_id' => $group->id,
                'attribute_data' => ['name' => new Text('New Arrivals')],
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
