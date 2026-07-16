<?php

namespace Modules\Catalog\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\Url;

/**
 * Seeds 50 demo fashion products with real demo images from
 * public/demo. Idempotent (keyed by slug).
 */
class Demo50ProductsSeeder extends Seeder
{
    public function run(): void
    {
        $currency = Currency::getDefault();
        $type = ProductType::first() ?? ProductType::create(['name' => 'Default']);
        $taxClass = TaxClass::getDefault();
        $language = Language::getDefault();
        $collection = config('lunar.media.collection', 'images');

        [$sizeValues, $colorValues, $sizeOption, $colorOption] = $this->options();
        $collections = $this->collections();

        // Single shared pool of demo photos at public/demo.
        $pool = $this->images();

        foreach ($this->catalog() as $i => $item) {
            $slug = Str::slug($item['name']).'-'.($i + 1);

            if (Product::whereHas('urls', fn ($q) => $q->where('slug', $slug))->exists()) {
                continue;
            }

            $product = Product::create([
                'product_type_id' => $type->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => $this->t($item['name'], $item['name_vi']),
                    'description' => $this->t($item['description'], $item['description_vi']),
                ],
            ]);

            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'SKU-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'stock' => random_int(5, 80),
                'unit_quantity' => 1,
                'tax_class_id' => $taxClass?->id,
            ]);

            Price::create([
                'price' => $item['price'],
                'currency_id' => $currency->id,
                'priceable_type' => $variant->getMorphClass(),
                'priceable_id' => $variant->id,
            ]);

            Url::create([
                'slug' => $slug,
                'element_type' => $product->getMorphClass(),
                'element_id' => $product->id,
                'default' => true,
                'language_id' => $language->id,
            ]);

            // Link the product to the Size/Color options so the admin variant
            // editor can render the value matrix (variants carry the values, but
            // the admin reads them via the product's assigned options).
            $product->productOptions()->syncWithoutDetaching([
                $sizeOption->id => ['position' => 1],
                $colorOption->id => ['position' => 2],
            ]);

            // Variant options (cycle through size/color).
            $variant->values()->syncWithoutDetaching([
                $sizeValues[$i % count($sizeValues)],
                $colorValues[$i % count($colorValues)],
            ]);

            // Media: at least 3 images per product from the shared demo pool.
            if ($pool) {
                $count = count($pool);
                for ($n = 0; $n < 3; $n++) {
                    $image = $pool[($i * 3 + $n) % $count];
                    $product->addMedia($image)->preservingOriginal()->toMediaCollection($collection);
                }
            }

            // Collections: always New Arrivals + gender + maybe Sale.
            $assign = [$collections['new-arrivals'], $collections[$item['gender']]];
            if ($item['on_sale']) {
                $assign[] = $collections['sale'];
            }
            $product->collections()->syncWithoutDetaching(array_filter($assign));
        }
    }

    /**
     * Build a translatable attribute value with English + Vietnamese. Falls
     * back to English when no VI translation is provided. Admins can add more
     * locales later via the Lunar product editor (translateAttribute).
     */
    protected function t(string $en, ?string $vi = null): TranslatedText
    {
        return new TranslatedText([
            'en' => new Text($en),
            'vi' => new Text($vi !== null && $vi !== '' ? $vi : $en),
        ]);
    }

    /**
     * @return array{0: array<int,int>, 1: array<int,int>, 2: ProductOption, 3: ProductOption}
     */
    protected function options(): array
    {
        $size = $this->option('Size', ['S', 'M', 'L', 'XL']);
        $color = $this->option('Color', ['Black', 'White', 'Beige', 'Gray', 'Navy', 'Red'], displayType: 'color');

        return [
            $size->values()->pluck('id')->all(),
            $color->values()->pluck('id')->all(),
            $size,
            $color,
        ];
    }

    /**
     * Shared + display-typed, matching DemoOptionsSeeder (the variant builder
     * only surfaces shared options; 'color' renders swatches).
     *
     * @param  array<int,string>  $values
     */
    protected function option(string $name, array $values, string $displayType = 'text'): ProductOption
    {
        $option = ProductOption::whereJsonContains('name->en', $name)->first()
            ?? ProductOption::create([
                'name' => ['en' => $name],
                'label' => ['en' => $name],
                'handle' => strtolower($name),
                'shared' => true,
                'display_type' => $displayType,
            ]);

        if (! $option->shared || $option->display_type !== $displayType) {
            $option->shared = true;
            $option->display_type = $displayType;
            $option->save();
        }

        foreach ($values as $value) {
            if (! $option->values()->whereJsonContains('name->en', $value)->exists()) {
                ProductOptionValue::create(['product_option_id' => $option->id, 'name' => ['en' => $value]]);
            }
        }

        return $option;
    }

    /**
     * @return array<string, int|null> handle => collection id
     */
    protected function collections(): array
    {
        $group = CollectionGroup::first() ?? CollectionGroup::create(['name' => 'Main', 'handle' => 'main']);
        $language = Language::getDefault();

        $defs = [
            'new-arrivals' => 'New Arrivals',
            'women' => 'Women',
            'men' => 'Men',
            'sale' => 'Sale',
        ];

        $ids = [];

        foreach ($defs as $slug => $name) {
            $collection = LunarCollection::whereHas('urls', fn ($q) => $q->where('slug', $slug))->first();

            if (! $collection) {
                $collection = LunarCollection::create([
                    'collection_group_id' => $group->id,
                    'attribute_data' => ['name' => new Text($name)],
                ]);

                Url::create([
                    'slug' => $slug,
                    'element_type' => $collection->getMorphClass(),
                    'element_id' => $collection->id,
                    'default' => true,
                    'language_id' => $language->id,
                ]);
            }

            $ids[$slug] = $collection->id;
        }

        return $ids;
    }

    /**
     * Absolute paths to every demo image in public/demo (jpg/jpeg/png).
     *
     * @return array<int, string>
     */
    protected function images(): array
    {
        $dir = public_path('demo');

        return collect(glob("{$dir}/*.{jpg,jpeg,png}", GLOB_BRACE) ?: [])
            ->sort()
            ->values()
            ->all();
    }

    /**
     * 50 fashion products (name, price in minor units, gender, sale flag).
     *
     * @return array<int, array{name:string, description:string, price:int, gender:string, on_sale:bool}>
     */
    protected function catalog(): array
    {
        $names = [
            'V-neck Cotton T-shirt', 'Ribbed Cotton-blend Top', 'Faux-leather Trousers',
            'Belt Wrap Dress', 'Double-button Trench Coat', 'Polarized Sunglasses',
            'Ramie Shirt with Pockets', 'Buttoned Cotton Shirt', 'Chest-pocket Overshirt',
            'Cotton Shopper Bag', 'Stretch Strap Top', 'Pleated Midi Skirt',
            'Oversized Wool Coat', 'Slim-fit Chinos', 'Linen Blend Blazer',
            'Knitted Turtleneck', 'High-waist Jeans', 'Floral Maxi Dress',
            'Cropped Denim Jacket', 'Cashmere Sweater', 'Tailored Suit Trousers',
            'Puffer Vest', 'Silk Camisole', 'Corduroy Overshirt',
            'Pleated Wide-leg Pants', 'Hooded Parka', 'Ribbed Knit Cardigan',
            'Leather Ankle Boots', 'Quilted Crossbody Bag', 'Wool-blend Scarf',
            'Striped Long-sleeve Tee', 'Bootcut Jeans', 'Satin Slip Dress',
            'Bomber Jacket', 'Cable-knit Jumper', 'Cargo Utility Pants',
            'Fitted Blazer Dress', 'Relaxed Linen Shirt', 'Pinstripe Waistcoat',
            'Faux-fur Coat', 'Jersey Jogger Pants', 'Halterneck Top',
            'Denim Mini Skirt', 'Quilted Liner Jacket', 'Mock-neck Bodysuit',
            'Tweed Mini Dress', 'Drawstring Shorts', 'Boucle Knit Top',
            'Wide-leg Trousers', 'Sequin Party Dress',
        ];

        // Vietnamese product names, parallel to $names (same order/index).
        $namesVi = [
            'Áo thun cotton cổ tim', 'Áo dệt kim gân cotton pha', 'Quần da giả',
            'Đầm quấn thắt lưng', 'Áo khoác trench hai hàng khuy', 'Kính râm phân cực',
            'Áo sơ mi vải gai có túi', 'Áo sơ mi cotton cài khuy', 'Áo khoác nhẹ túi ngực',
            'Túi tote cotton', 'Áo hai dây co giãn', 'Chân váy midi xếp ly',
            'Áo khoác len dáng rộng', 'Quần chinos ôm', 'Blazer vải lanh pha',
            'Áo len cổ lọ', 'Quần jeans lưng cao', 'Đầm maxi hoa',
            'Áo khoác jeans lửng', 'Áo len cashmere', 'Quần âu may đo',
            'Áo gile phao', 'Áo hai dây lụa', 'Áo khoác nhung tăm',
            'Quần ống rộng xếp ly', 'Áo parka có mũ', 'Áo cardigan dệt kim gân',
            'Bốt da cổ ngắn', 'Túi đeo chéo chần bông', 'Khăn len pha',
            'Áo thun dài tay kẻ sọc', 'Quần jeans ống loe', 'Đầm hai dây satin',
            'Áo khoác bomber', 'Áo len bện thừng', 'Quần cargo tiện dụng',
            'Đầm blazer ôm dáng', 'Áo sơ mi lanh dáng suông', 'Áo gile kẻ sọc nhỏ',
            'Áo khoác lông giả', 'Quần jogger vải jersey', 'Áo cổ yếm',
            'Chân váy jeans ngắn', 'Áo khoác lót chần bông', 'Bodysuit cổ giả',
            'Đầm mini vải tweed', 'Quần short dây rút', 'Áo dệt kim bouclé',
            'Quần ống rộng', 'Đầm dự tiệc kim sa',
        ];

        $descriptions = [
            ['Crafted from premium fabric for everyday comfort and style.',
                'Chế tác từ chất liệu cao cấp, thoải mái và phong cách cho mỗi ngày.'],
            ['A versatile wardrobe staple with a flattering, modern cut.',
                'Món đồ tủ đa năng với phom dáng hiện đại, tôn dáng.'],
            ['Designed to move with you — soft, durable, and breathable.',
                'Thiết kế chuyển động cùng bạn — mềm mại, bền và thoáng khí.'],
            ['Timeless silhouette that pairs effortlessly with any look.',
                'Phom dáng vượt thời gian, dễ dàng phối cùng mọi phong cách.'],
            ['Elevated detailing and a relaxed fit for all-day wear.',
                'Chi tiết tinh tế cùng phom dáng thoải mái cho cả ngày dài.'],
        ];

        $catalog = [];

        foreach ($names as $i => $name) {
            [$descEn, $descVi] = $descriptions[$i % count($descriptions)];

            $catalog[] = [
                'name' => $name,
                'name_vi' => $namesVi[$i] ?? $name,
                'description' => $descEn,
                'description_vi' => $descVi,
                'price' => random_int(15, 250) * 100, // 15.00 – 250.00
                'gender' => $i % 4 === 0 ? 'men' : 'women',
                'on_sale' => $i % 5 === 0,
            ];
        }

        return $catalog;
    }
}
