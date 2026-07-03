<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\App;
use Lunar\Models\Language;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Bilingual product content (TODO #10): products carry EN + VI translations in
 * attribute_data, a `vi` Language exists, and the storefront resolves the name
 * in the visitor's locale via Lunar `translateAttribute()`.
 */
class ProductTranslationTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_base_data_seeds_both_en_and_vi_languages(): void
    {
        // seedBaseData() runs automatically via the trait's setUp hook.
        $this->assertTrue(Language::where('code', 'en')->exists());
        $this->assertTrue(Language::where('code', 'vi')->exists());
        $this->assertSame('en', Language::getDefault()->code, 'English stays the Lunar default.');
    }

    public function test_translate_attribute_resolves_per_locale(): void
    {
        $product = $this->createProduct([
            'name' => 'Linen Shirt',
            'name_vi' => 'Áo sơ mi vải lanh',
        ]);

        App::setLocale('en');
        $this->assertSame('Linen Shirt', $product->translateAttribute('name'));

        App::setLocale('vi');
        $this->assertSame('Áo sơ mi vải lanh', $product->translateAttribute('name'));
    }

    public function test_storefront_product_page_shows_vietnamese_name_when_locale_is_vi(): void
    {
        $product = $this->createProduct([
            'name' => 'Denim Jacket',
            'name_vi' => 'Áo khoác jeans',
        ]);
        $slug = $product->defaultUrl->slug;

        // The `storefront` middleware group resolves locale from the session
        // (SetStorefrontLocale), so a stored VI choice renders Vietnamese.
        $this->withSession(['locale' => 'vi'])
            ->get("/products/{$slug}")
            ->assertOk()
            ->assertSee('Áo khoác jeans')
            ->assertDontSee('Denim Jacket');
    }

    public function test_api_returns_english_name_by_default(): void
    {
        $product = $this->createProduct([
            'name' => 'Wool Coat',
            'name_vi' => 'Áo khoác len',
        ]);
        $slug = $product->defaultUrl->slug;

        // No ?locale / Accept-Language → falls back to the default locale (en).
        $this->getJson("/api/v1/products/{$slug}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Wool Coat');
    }

    public function test_api_returns_vietnamese_name_via_locale_query(): void
    {
        $product = $this->createProduct([
            'name' => 'Wool Coat',
            'name_vi' => 'Áo khoác len',
        ]);
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}?locale=vi")
            ->assertOk()
            ->assertJsonPath('data.name', 'Áo khoác len');
    }

    public function test_api_returns_vietnamese_name_via_accept_language_header(): void
    {
        $product = $this->createProduct([
            'name' => 'Wool Coat',
            'name_vi' => 'Áo khoác len',
        ]);
        $slug = $product->defaultUrl->slug;

        $this->getJson("/api/v1/products/{$slug}", ['Accept-Language' => 'vi'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Áo khoác len');
    }
}
