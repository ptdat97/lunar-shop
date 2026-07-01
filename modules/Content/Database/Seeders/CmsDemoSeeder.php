<?php

namespace Modules\Content\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Asset;
use Lunar\Models\Product;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Lookbook;
use Modules\Content\Models\Page;
use Modules\Content\Models\Redirect;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Demo content for the CMS features: Pages, Banners, Lookbooks (+ items) and
 * Redirects. Image fields hold Media Library asset ids (resolved by media
 * name), so MediaLibraryDemoSeeder must run first.
 *
 * Idempotent: keyed by slug / unique columns via updateOrCreate.
 */
class CmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedBanners();
        $this->seedLookbooks();
        $this->seedRedirects();
    }

    protected function seedPages(): void
    {
        $pages = [
            [
                'title' => 'About Us',
                'slug' => 'about-us',
                'featured_image' => $this->assetId('About Us Cover'),
                'content' => '<h2>Our Story</h2><p>We craft modern fashion essentials for everyday confidence — designed responsibly, made to last.</p>',
                'meta_title' => 'About Us — Modave Fashion',
                'meta_description' => 'Learn about our story, values and the people behind the brand.',
                'published' => true,
            ],
            [
                'title' => 'Size Guide',
                'slug' => 'size-guide',
                'featured_image' => $this->assetId('Size Guide Cover'),
                'content' => '<h2>Size Guide</h2><p>Find your perfect fit with our detailed measurements for tops, bottoms and outerwear.</p>',
                'meta_title' => 'Size Guide',
                'meta_description' => 'How to measure and choose the right size.',
                'published' => true,
            ],
            [
                'title' => 'Shipping & Returns',
                'slug' => 'shipping-returns',
                'featured_image' => null,
                'content' => '<h2>Shipping & Returns</h2><p>Free shipping over $50. Easy 30-day returns on all orders.</p>',
                'meta_title' => 'Shipping & Returns',
                'meta_description' => 'Shipping options, delivery times and our return policy.',
                'published' => true,
            ],
        ];

        foreach ($pages as $data) {
            Page::updateOrCreate(['slug' => $data['slug']], $data);
        }
    }

    protected function seedBanners(): void
    {
        $banners = [
            [
                'title' => 'New Season Womenswear',
                'subtitle' => 'Discover the latest arrivals',
                'button_text' => 'Shop Women',
                'button_url' => '/collections/women',
                'image' => $this->assetId('Discover Women'),
                'mobile_image' => $this->assetId('Discover Women'),
                'position' => 'left',
                'active' => true,
                'sort' => 1,
            ],
            [
                'title' => 'Menswear Edit',
                'subtitle' => 'Tailored for every day',
                'button_text' => 'Shop Men',
                'button_url' => '/collections/men',
                'image' => $this->assetId('Discover Men'),
                'mobile_image' => $this->assetId('Discover Men'),
                'position' => 'right',
                'active' => true,
                'sort' => 2,
            ],
            [
                'title' => 'Activewear Drop',
                'subtitle' => 'Move with confidence',
                'button_text' => 'Explore',
                'button_url' => '/collections/new-arrivals',
                'image' => $this->assetId('Activewear Slider 1'),
                'mobile_image' => $this->assetId('Activewear Slider 2'),
                'position' => 'center',
                'active' => true,
                'sort' => 3,
            ],
        ];

        foreach ($banners as $data) {
            Banner::updateOrCreate(['title' => $data['title']], $data);
        }
    }

    protected function seedLookbooks(): void
    {
        $products = Product::query()->take(12)->pluck('id')->all();

        $lookbooks = [
            [
                'title' => 'Spring Essentials',
                'slug' => 'spring-essentials',
                'cover_image' => $this->assetId('Lookbook Spring'),
                'description' => 'Light layers and fresh tones for the new season.',
                'published' => true,
            ],
            [
                'title' => 'Summer Heat',
                'slug' => 'summer-heat',
                'cover_image' => $this->assetId('Lookbook Summer'),
                'description' => 'Breezy fits for warm days.',
                'published' => true,
            ],
            [
                'title' => 'Street Style',
                'slug' => 'street-style',
                'cover_image' => $this->assetId('Lookbook Street'),
                'description' => 'Urban looks with an edge.',
                'published' => false,
            ],
        ];

        foreach ($lookbooks as $i => $data) {
            $lookbook = Lookbook::updateOrCreate(['slug' => $data['slug']], $data);

            // Attach 3 products per lookbook (rebuild items to stay idempotent).
            $lookbook->items()->delete();
            $chunk = array_slice($products, $i * 3, 3);

            foreach ($chunk as $sort => $productId) {
                $lookbook->items()->create([
                    'product_id' => $productId,
                    'caption' => 'Featured look ' . ($sort + 1),
                    'sort' => $sort,
                ]);
            }
        }
    }

    protected function seedRedirects(): void
    {
        $redirects = [
            ['old_url' => '/old-about', 'new_url' => '/about-us', 'status_code' => 301, 'active' => true],
            ['old_url' => '/sizing', 'new_url' => '/size-guide', 'status_code' => 301, 'active' => true],
            ['old_url' => '/sale-2025', 'new_url' => '/collections/sale', 'status_code' => 302, 'active' => true],
        ];

        foreach ($redirects as $data) {
            Redirect::updateOrCreate(['old_url' => $data['old_url']], $data);
        }
    }

    /**
     * Resolve a Media Library asset id by its media name (set in the seeder).
     */
    protected function assetId(string $mediaName): ?int
    {
        $media = Media::query()->where('name', $mediaName)->first();

        return $media?->model instanceof Asset ? $media->model->id : null;
    }
}
