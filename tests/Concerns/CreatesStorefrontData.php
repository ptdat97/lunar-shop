<?php

namespace Tests\Concerns;

use App\Models\User;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\Url;
use Modules\Catalog\Database\Seeders\BaseDataSeeder;

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
     * A published product with one in-stock, priced variant + a URL slug.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createProduct(array $attributes = []): Product
    {
        $name = $attributes['name'] ?? 'Test Tee';
        $slug = $attributes['slug'] ?? 'test-tee-'.uniqid();
        $price = $attributes['price'] ?? 1999; // minor units
        $stock = $attributes['stock'] ?? 25;

        $product = Product::create([
            'product_type_id' => ProductType::first()?->id ?? ProductType::create(['name' => 'General'])->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text($name)],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => $attributes['sku'] ?? 'SKU-'.strtoupper(substr(uniqid(), -6)),
            'stock' => $stock,
            'unit_quantity' => 1,
            'tax_class_id' => TaxClass::getDefault()?->id,
        ]);

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

        return $product->fresh(['variants', 'urls']);
    }

    /**
     * Attach a simple size chart (S/M/L rows) to a product for size-intelligence
     * tests. Returns the product.
     */
    protected function attachSizeChart(Product $product): Product
    {
        $chart = \Modules\Catalog\Models\SizeChart::create([
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
    protected function seedLocations(): \Modules\Customer\Models\Province
    {
        $hcm = \Modules\Customer\Models\Province::create(['code' => '79', 'name' => 'Thành phố Hồ Chí Minh']);
        $hcm->wards()->createMany([
            ['code' => '79001', 'name' => 'Phường Bến Nghé'],
            ['code' => '79002', 'name' => 'Phường Bến Thành'],
        ]);
        \Modules\Customer\Models\Province::create(['code' => '01', 'name' => 'Thành phố Hà Nội'])
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
            'country_id' => \Lunar\Models\Country::query()->value('id'),
            'contact_email' => 'buyer@example.com',
            'contact_phone' => '0900000000',
        ], $overrides);
    }
}
