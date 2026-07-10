<?php

namespace Tests\Feature;

use Modules\Catalog\Models\Review;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Page;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Pages, banners and reviews serialise through a JsonResource.
 *
 * All three used to build their payload inline — pages and banners handed the
 * raw Eloquent model to `response()->json()`, so every column (including the
 * `published` / `active` flags) was part of the contract by accident, and any
 * column added later would have leaked. Reviews were also unpaginated: a popular
 * product returned every review it had ever collected.
 */
class ContentAndReviewApiTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_pages_expose_only_the_documented_fields(): void
    {
        $this->seedBaseData();

        Page::create([
            'title' => 'About us',
            'slug' => 'about-us',
            'content' => '<p>Hello</p>',
            'published' => true,
        ]);

        $page = $this->getJson('/api/v1/pages')->assertOk()->json('data.0');

        $this->assertSame(
            ['id', 'title', 'slug', 'featured_image', 'content', 'seo', 'updated_at'],
            array_keys($page),
        );

        // Server bookkeeping must not travel with the payload.
        $this->assertArrayNotHasKey('published', $page);
        $this->assertArrayNotHasKey('created_at', $page);
    }

    public function test_pages_are_paginated_with_the_shared_meta(): void
    {
        $this->seedBaseData();

        foreach (range(1, 3) as $i) {
            Page::create(['title' => "P{$i}", 'slug' => "p{$i}", 'content' => 'x', 'published' => true]);
        }

        $response = $this->getJson('/api/v1/pages?per_page=2')->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertSame(
            ['page', 'per_page', 'last_page', 'total'],
            array_keys($response->json('meta')),
        );
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_an_unpublished_page_is_not_listed_or_fetchable(): void
    {
        $this->seedBaseData();

        Page::create(['title' => 'Draft', 'slug' => 'draft', 'content' => 'x', 'published' => false]);

        $this->getJson('/api/v1/pages')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/pages/draft')->assertStatus(404)->assertJsonPath('message', 'Page not found.');
    }

    public function test_a_single_page_is_returned_through_the_resource(): void
    {
        $this->seedBaseData();

        Page::create([
            'title' => 'About us',
            'slug' => 'about-us',
            'content' => '<p>Hello</p>',
            'meta_title' => 'About',
            'published' => true,
        ]);

        $this->getJson('/api/v1/pages/about-us')
            ->assertOk()
            ->assertJsonPath('data.slug', 'about-us')
            ->assertJsonPath('data.seo.meta_title', 'About');
    }

    public function test_banners_hide_server_bookkeeping(): void
    {
        $this->seedBaseData();

        Banner::create([
            'title' => 'Summer sale',
            'position' => 'home-hero',
            'image' => 'banners/summer.jpg',
            'button_text' => 'Shop',
            'button_url' => '/collections/sale',
            'active' => true,
            'sort' => 1,
        ]);

        $banner = $this->getJson('/api/v1/banners')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('active', $banner);
        $this->assertArrayNotHasKey('sort', $banner);
        $this->assertSame(['text' => 'Shop', 'url' => '/collections/sale'], $banner['button']);
    }

    public function test_a_banner_without_a_link_has_a_null_button(): void
    {
        $this->seedBaseData();

        Banner::create(['title' => 'Plain', 'position' => 'home-hero', 'image' => 'b.jpg', 'active' => true, 'sort' => 1]);

        $this->getJson('/api/v1/banners')->assertOk()->assertJsonPath('data.0.button', null);
    }

    public function test_reviews_are_paginated_with_the_shared_meta(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        foreach (range(1, 3) as $i) {
            Review::create([
                'product_id' => $product->id,
                'author' => "Reviewer {$i}",
                'rating' => 5,
                'body' => 'Great',
                'approved' => true,
            ]);
        }

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews?per_page=2")->assertOk();

        $response->assertJsonCount(2, 'data');
        $this->assertSame(
            ['page', 'per_page', 'last_page', 'total'],
            array_keys($response->json('meta')),
        );
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_the_rating_roll_up_moved_to_summary(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        Review::create(['product_id' => $product->id, 'author' => 'A', 'rating' => 4, 'approved' => true]);
        Review::create(['product_id' => $product->id, 'author' => 'B', 'rating' => 2, 'approved' => true]);

        $response = $this->getJson("/api/v1/products/{$product->id}/reviews")->assertOk();

        $this->assertSame(2, $response->json('summary.count'));
        // round(3.0, 2) JSON-encodes as `3`, so compare numerically.
        $this->assertEqualsWithDelta(3.0, $response->json('summary.average'), 0.001);
    }

    public function test_the_product_payload_still_carries_the_review_summary(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        Review::create(['product_id' => $product->id, 'author' => 'A', 'rating' => 5, 'approved' => true]);

        // The storefront reads the roll-up here, not from the reviews endpoint.
        $this->getJson('/api/v1/products/'.$product->defaultUrl->slug)
            ->assertOk()
            ->assertJsonPath('data.reviews.count', 1);
    }

    public function test_unapproved_reviews_are_never_listed(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct();

        Review::create(['product_id' => $product->id, 'author' => 'Spam', 'rating' => 1, 'approved' => false]);

        $this->getJson("/api/v1/products/{$product->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
