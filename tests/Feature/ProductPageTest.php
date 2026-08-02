<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Storefront product page (SSR Blade) smoke render — including the Swiper gallery
 * markup the vanilla enhancer (_gallery.js) initialises.
 */
class ProductPageTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_product_page_renders_with_swiper_gallery_markup(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['name' => 'Linen Shirt']);
        $product->addMedia(UploadedFile::fake()->image('a.jpg', 800, 1200))->toMediaCollection('images');
        $product->addMedia(UploadedFile::fake()->image('b.jpg', 800, 1200))->toMediaCollection('images');
        $slug = $product->defaultUrl->slug;

        $html = $this->get("/products/{$slug}")
            ->assertOk()
            ->assertSee('Linen Shirt')
            ->assertSee('id="product-gallery"', false)
            ->assertSee('data-gallery-main', false)     // Swiper main slider hook
            ->assertSee('data-gallery-thumbs', false)   // thumbnail strip (2+ images)
            ->assertSee('product-gallery__item', false) // PhotoSwipe target
            ->getContent();

        // Thumbs strip renders BEFORE the main slider (left/above in layout).
        $this->assertLessThan(
            strpos($html, 'data-gallery-main'),
            strpos($html, 'data-gallery-thumbs'),
            'thumbs should render before the main slider'
        );

        // Thumbs use the small conversion, not large.
        $this->assertStringContainsString('-small.', $html, 'thumbs should use the small conversion');
    }

    public function test_product_page_renders_without_images(): void
    {
        // No media attached → gallery shows the "No image" fallback, no crash.
        $this->seedBaseData();
        $product = $this->createProduct();
        $slug = $product->defaultUrl->slug;

        $this->get("/products/{$slug}")
            ->assertOk()
            ->assertSee('No image');
    }

    public function test_product_page_uses_sellable_stock_for_the_selected_variant(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['stock' => 2]);
        $product->skus()->first()->update(['committed' => 2]);
        $slug = $product->defaultUrl->slug;

        $this->get("/products/{$slug}")
            ->assertOk()
            ->assertSee('Out of stock')
            ->assertSee('data-add-to-cart-btn disabled', false)
            ->assertSee('data-initial-out="1"', false);
    }
}
