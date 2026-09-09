<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Lunar\Core\Contracts\Actions\Products\AdjustsStock;
use Lunar\Core\Models\Country;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Language;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Lunar\Core\Models\ProductType;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\TaxClass;
use Lunar\Core\Models\Url;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;
use Modules\Catalog\Models\SizeChart;
use Modules\Customer\Models\Province;

/**
 * Test helpers: seed Lunar essentials (channel/currency/tax/countries) and
 * build a published product with a priced, in-stock variant. Keeps feature
 * tests focused on behaviour rather than fixture wiring.
 */
trait CreatesStorefrontData
{
    /**
     * Auto-booted by Laravel's TestCase: every test using this trait gets the
     * Lunar essentials (channel/currency/tax/countries). The storefront session
     * middleware needs a default currency on any web/api/v1 request, so seed it
     * up front rather than per-test.
     */
    protected function setUpCreatesStorefrontData(): void
    {
        $this->seedBaseData();
    }

    protected function seedBaseData(): void
    {
        $this->seed(BaseDataSeeder::class);
    }

    /**
     * A published product with one in-stock, priced SKU (the purchasable) + a
     * URL slug.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createProduct(array $attributes = []): Product
    {
        $name = $attributes['name'] ?? 'Test Tee';
        $slug = $attributes['slug'] ?? 'test-tee-'.uniqid();
        $price = $attributes['price'] ?? 1999; // minor units
        $stock = $attributes['stock'] ?? 25;

        // Since Lunar 2.0 `name` is a plain `{locale: text}` JSON column, not a
        // field type inside attribute_data. A Vietnamese name simply adds a
        // second key, so tests can still exercise per-locale content.
        $names = ['en' => $name];

        if (isset($attributes['name_vi'])) {
            $names['vi'] = $attributes['name_vi'];
        }

        $product = Product::create([
            'product_type_id' => ProductType::first()?->id ?? ProductType::create(['name' => 'General'])->id,
            // Products carry a `status` state; `enabled` is the VARIANT flag.
            // Leaving this out silently creates a draft product, which then
            // fails every purchasability check downstream.
            'status' => 'published',
            'name' => $names,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $attributes['sku'] ?? 'SKU-'.strtoupper(substr(uniqid(), -6)),
            'unit_quantity' => 1,
            'shippable' => true,
            'selling_policy' => 'in_stock',
            'enabled' => true,
            'tax_class_id' => TaxClass::getDefault()?->id,
        ]);

        // Stock goes in through Lunar's action so the level, the rollup and the
        // movement ledger agree — the same path production takes. Writing
        // `stock_on_hand` directly would leave the ledger empty and the next
        // recompute would zero it.
        if ($stock !== 0) {
            app(AdjustsStock::class)->execute($variant, $stock, 'test fixture');
        }

        // Option axes, when the test asks for them. Values are matched by name
        // so a test can name the same colour twice and get one shared value —
        // which is what a real catalogue does.
        foreach ($attributes['options'] ?? [] as $optionName => $valueName) {
            $variant->values()->syncWithoutDetaching([
                $this->optionValue($product, (string) $optionName, (string) $valueName)->id,
            ]);
        }

        Price::create([
            'price' => $price,
            'currency_id' => Currency::getDefault()->id,
            'priceable_type' => $variant->getMorphClass(),
            'priceable_id' => $variant->id,
        ]);

        Url::create([
            'slug' => $slug,
            'element_type' => $product->getMorphClass(),
            'element_id' => $product->id,
            'default' => true,
            'language_id' => Language::getDefault()->id,
        ]);

        return $product->fresh(['variants.values', 'productOptions.values', 'urls']);
    }

    /**
     * Set a variant's on-hand stock to an absolute figure.
     *
     * Goes through Lunar's `AdjustStock` by delta, because `stock_on_hand` is a
     * ROLLUP of the per-location levels — writing it directly leaves the level
     * and the ledger untouched, and the next recompute silently reverts it.
     * That is the same class of trap as writing an order's `payment_status` by
     * hand (docs/guides/upgrade-lunar-2.0.md §9.6).
     */
    protected function setStock(ProductVariant $variant, int $quantity): ProductVariant
    {
        $delta = $quantity - (int) $variant->stock_on_hand;

        if ($delta !== 0) {
            app(AdjustsStock::class)->execute($variant, $delta, 'test fixture');
        }

        return $variant->refresh();
    }

    /**
     * A shared option value by name, creating the option and the value if this
     * is the first test to ask for them, and attaching the option to the product
     * so the picker can find its axes.
     */
    protected function optionValue(Product $product, string $optionName, string $valueName): ProductOptionValue
    {
        $option = ProductOption::query()->whereJsonContains('name->en', $optionName)->first()
            ?? ProductOption::create([
                'name' => ['en' => $optionName],
                'label' => ['en' => $optionName],
                'handle' => Str::slug($optionName).'-'.uniqid(),
                'shared' => true,
                'type' => 'text',
            ]);

        $product->productOptions()->syncWithoutDetaching([
            $option->id => ['position' => $product->productOptions()->count() + 1],
        ]);

        return $option->values()->whereJsonContains('name->en', $valueName)->first()
            ?? $option->values()->create([
                'name' => ['en' => $valueName],
            ]);
    }

    /**
     * Attach a simple size chart (S/M/L rows) to a product for size-intelligence
     * tests. Returns the product.
     */
    protected function attachSizeChart(Product $product): Product
    {
        $chart = SizeChart::create([
            'name' => 'Tops', 'category' => 'tops', 'active' => true,
        ]);

        foreach ([
            ['size' => 'S', 'fit' => 'regular', 'bust' => 82, 'waist' => 64, 'hip' => 88],
            ['size' => 'M', 'fit' => 'regular', 'bust' => 88, 'waist' => 70, 'hip' => 94],
            ['size' => 'L', 'fit' => 'regular', 'bust' => 94, 'waist' => 76, 'hip' => 100],
        ] as $row) {
            $chart->rows()->create($row);
        }

        $product->sizeChart()->sync([$chart->id]);

        return $product->fresh();
    }

    /**
     * Seed a couple of provinces + wards (small fixture; the full dataset is
     * 3.3k wards and unnecessary for tests).
     */
    protected function seedLocations(): Province
    {
        $hcm = Province::create(['code' => '79', 'name' => 'Thành phố Hồ Chí Minh']);
        $hcm->wards()->createMany([
            ['code' => '79001', 'name' => 'Phường Bến Nghé'],
            ['code' => '79002', 'name' => 'Phường Bến Thành'],
        ]);
        Province::create(['code' => '01', 'name' => 'Thành phố Hà Nội'])
            ->wards()->create(['code' => '01001', 'name' => 'Phường Hoàn Kiếm']);

        return $hcm;
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => bcrypt('password123'),
        ], $attributes));
    }

    /**
     * A valid shipping address payload for checkout / address-book tests.
     *
     * @return array<string, mixed>
     */
    protected function shippingPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mai',
            'last_name' => 'Tran',
            'line_one' => '1 Le Loi',
            'state' => 'Thành phố Hồ Chí Minh', // Tỉnh/Thành
            'city' => 'Phường Bến Nghé',         // Phường/Xã
            'country_id' => Country::query()->value('id'),
            'contact_email' => 'buyer@example.com',
            'contact_phone' => '0900000000',
        ], $overrides);
    }
}
