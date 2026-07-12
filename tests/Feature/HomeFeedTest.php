<?php

namespace Tests\Feature;

use Modules\Content\Models\PageSection;
use Modules\Content\Services\SectionRenderer;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * GET /api/v1/home-feed — the Blade home page as JSON, for headless clients.
 *
 * The reason this endpoint was deferred rather than shipped early: a section's
 * data provider returns view data holding Eloquent models (Product, Discount),
 * and serialising that straight to JSON would put model internals — every
 * column, every loaded relation — into a public contract we then have to keep.
 * So each dynamic section maps through the same API Resource the rest of
 * /api/v1 uses, and a section with no such mapping is OMITTED, not dumped raw.
 * These tests hold that line.
 */
class HomeFeedTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_it_returns_the_home_sections_in_admin_order(): void
    {
        $this->seedBaseData();

        PageSection::query()->delete();
        $this->section('iconbox', sort: 1);
        $this->section('hero-slider', sort: 0);

        $types = $this->getJson('/api/v1/home-feed')
            ->assertOk()
            ->json('data.*.type');

        // Sorted by `sort`, not by insertion — the admin decides the order.
        $this->assertSame(['hero-slider', 'iconbox'], $types);
    }

    public function test_a_static_section_carries_its_admin_authored_settings(): void
    {
        $this->seedBaseData();

        PageSection::query()->delete();
        $this->section('hero-slider', settings: [
            'slides' => [['title' => 'Fresh Fashion Finds', 'image' => '/demo/a.jpg']],
        ]);

        $section = $this->getJson('/api/v1/home-feed')->assertOk()->json('data.0');

        $this->assertSame('Fresh Fashion Finds', $section['settings']['slides'][0]['title']);
    }

    public function test_a_dynamic_section_serialises_products_through_the_shared_resource(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['name' => 'Linen Shirt']);

        PageSection::query()->delete();
        $this->section('product-tabs', settings: [
            'tabs' => [['label' => 'New Arrivals', 'product_ids' => [$product->id]]],
        ]);

        $tab = $this->getJson('/api/v1/home-feed')
            ->assertOk()
            ->json('data.0.data.tabs.0');

        $this->assertSame('New Arrivals', $tab['label']);
        $this->assertSame('Linen Shirt', $tab['products'][0]['name']);

        // The ProductResource contract — the SAME shape /api/v1/products returns,
        // so a client has one product type, not one per endpoint.
        $this->assertSame(
            ['id', 'name', 'slug', 'description', 'thumbnail', 'hover_thumbnail', 'brand',
                'variants', 'images', 'availability', 'promotion', 'reviews'],
            array_keys($tab['products'][0]),
        );
    }

    public function test_it_never_leaks_eloquent_internals(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        PageSection::query()->delete();
        $this->section('product-tabs', settings: [
            'tabs' => [['label' => 'New', 'product_ids' => [$product->id]]],
        ]);

        $card = $this->getJson('/api/v1/home-feed')
            ->assertOk()
            ->json('data.0.data.tabs.0.products.0');

        // Lunar's raw columns. If a model ever went out unmapped these appear,
        // and from then on removing one is a breaking change.
        foreach (['attribute_data', 'product_type_id', 'created_at', 'updated_at', 'status'] as $internal) {
            $this->assertArrayNotHasKey($internal, $card);
        }
    }

    /**
     * The guard. A dynamic section (has a data provider) with no serializer must
     * be dropped from the feed — silence is safe, a raw model is not.
     *
     * Mutation-check: make SectionRenderer::sectionPayload() serialise an
     * unmapped section instead of returning null, and this test goes red.
     */
    public function test_a_dynamic_section_without_a_serializer_is_omitted(): void
    {
        $this->seedBaseData();

        PageSection::query()->delete();
        $this->section('iconbox');
        $this->section('bare-dynamic', sort: 1);

        // A section type whose provider hands back an Eloquent model, and which
        // nobody has said how to serialise.
        app(SectionRenderer::class)->provide(
            'bare-dynamic',
            fn () => ['product' => $this->createProduct()],
        );

        $types = $this->getJson('/api/v1/home-feed')->assertOk()->json('data.*.type');

        $this->assertNotContains('bare-dynamic', $types);
        $this->assertSame(['iconbox'], $types);
    }

    /**
     * Create a home section. Bypasses the section-config cache the same way the
     * app does — PageSection model events bust it on write.
     *
     * @param  array<string, mixed>  $settings
     */
    private function section(string $type, array $settings = [], int $sort = 0): PageSection
    {
        return PageSection::create([
            'page_handle' => 'home',
            'type' => $type,
            'settings' => $settings,
            'sort' => $sort,
            'enabled' => true,
        ]);
    }
}
