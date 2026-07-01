<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;
use Lunar\Models\Url;
use Modules\Catalog\Models\ProductMaterial;

/**
 * Seeds a few products that each carry the full S/M/L/XL size run (one variant
 * per size) — useful demo realism for the variant picker. Their size chart is
 * assigned by SizeIntelligenceDemoSeeder (which runs after this).
 *
 * Idempotent (keyed by slug). Complements Demo50ProductsSeeder (single-variant).
 */
class MultiSizeProductsSeeder extends Seeder
{
    /** Sizes to create one variant for. */
    protected const SIZES = ['S', 'M', 'L', 'XL'];

    public function run(): void
    {
        $currency = Currency::getDefault();
        $type = ProductType::first() ?? ProductType::create(['name' => 'Default']);
        $taxClass = TaxClass::getDefault();
        $language = Language::getDefault();
        $mediaCollection = config('lunar.media.collection', 'images');

        $sizeOption = ProductOption::whereJsonContains('name->en', 'Size')->first();
        $sizeValues = $this->sizeValueIds();      // ['S'=>id, ...]
        $collections = $this->collectionIds();
        $material = [
            'material' => 'Cotton', 'composition' => '95% Cotton, 5% Elastane',
            'fabric_weight' => '200 gsm', 'stretch' => 'slight', 'transparency' => 'opaque',
            'lining' => 'none', 'care_instruction' => 'Machine wash cold, hang dry, warm iron.',
        ];

        foreach ($this->catalog() as $idx => $item) {
            $slug = Str::slug($item['name']);

            if (Product::whereHas('urls', fn ($q) => $q->where('slug', $slug))->exists()) {
                continue;
            }

            $product = Product::create([
                'product_type_id' => $type->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text($item['name']),
                    'description' => new Text($item['description']),
                ],
            ]);

            Url::create([
                'slug' => $slug,
                'element_type' => $product->getMorphClass(),
                'element_id' => $product->id,
                'default' => true,
                'language_id' => $language->id,
            ]);

            // Link the product to the Size option so the admin variant editor
            // can render the value matrix (variants carry the values, but the
            // admin reads them via the product's assigned options).
            if ($sizeOption) {
                $product->productOptions()->syncWithoutDetaching([$sizeOption->id => ['position' => 1]]);
            }

            // One variant per size, each with its own price and size value.
            foreach (self::SIZES as $size) {
                $variant = $product->variants()->create([
                    'sku' => 'MS-' . strtoupper(Str::substr($slug, 0, 6)) . '-' . $size,
                    'stock' => random_int(5, 60),
                    'unit_quantity' => 1,
                    'tax_class_id' => $taxClass?->id,
                ]);

                Price::create([
                    'price' => $item['price'],
                    'currency_id' => $currency->id,
                    'priceable_type' => $variant->getMorphClass(),
                    'priceable_id' => $variant->id,
                ]);

                if (isset($sizeValues[$size])) {
                    $variant->values()->syncWithoutDetaching([$sizeValues[$size]]);
                }
            }

            ProductMaterial::updateOrCreate(['product_id' => $product->id], $material);

            // Media from the matching gender pool (3 images).
            $pool = $this->images($item['gender'] === 'men' ? 'mens' : 'womens', $item['gender'] === 'men' ? 'men-' : 'women-');
            if ($pool) {
                for ($m = 0; $m < 3; $m++) {
                    $product->addMedia($pool[($idx * 3 + $m) % count($pool)])
                        ->preservingOriginal()
                        ->toMediaCollection($mediaCollection);
                }
            }

            $assign = array_filter([
                $collections['new-arrivals'] ?? null,
                $collections[$item['gender']] ?? null,
            ]);
            $product->collections()->syncWithoutDetaching($assign);
        }
    }

    /**
     * @return array<int, array{name:string, description:string, price:int, gender:string}>
     */
    protected function catalog(): array
    {
        return [
            ['name' => 'Essential Fit Tee (All Sizes)', 'description' => 'Our signature tee in a full size run S–XL with a true-to-size cut.', 'price' => 2500, 'gender' => 'women'],
            ['name' => 'Tailored Stretch Shirt (All Sizes)', 'description' => 'A crisp stretch shirt available across every size for the perfect fit.', 'price' => 4900, 'gender' => 'men'],
            ['name' => 'High-rise Skinny Jeans (All Sizes)', 'description' => 'Sculpting high-rise jeans with a complete S–XL size range.', 'price' => 6900, 'gender' => 'women'],
            ['name' => 'Everyday Knit Jumper (All Sizes)', 'description' => 'Soft knit jumper offered in the full size run for any body type.', 'price' => 5500, 'gender' => 'men'],
        ];
    }

    /**
     * @return array<string, int>  size label => option value id
     */
    protected function sizeValueIds(): array
    {
        $option = ProductOption::whereJsonContains('name->en', 'Size')->first();

        if (! $option) {
            return [];
        }

        return $option->values->mapWithKeys(
            fn ($v) => [$v->translate('name') => $v->id],
        )->all();
    }

    /**
     * @return array<string, int>  slug => collection id
     */
    protected function collectionIds(): array
    {
        $ids = [];

        foreach (['new-arrivals', 'women', 'men'] as $slug) {
            $ids[$slug] = LunarCollection::whereHas('urls', fn ($q) => $q->where('slug', $slug))->value('id');
        }

        return $ids;
    }

    /**
     * Real demo images from public/demo (the gender args are kept for the call
     * site but no longer scope the pool — all products share the demo folder).
     *
     * @return array<int, string>
     */
    protected function images(string $folder = '', string $prefix = ''): array
    {
        $dir = public_path('demo');

        return collect(glob("{$dir}/*.{jpg,jpeg,png}", GLOB_BRACE) ?: [])
            ->sort()
            ->values()
            ->all();
    }
}
