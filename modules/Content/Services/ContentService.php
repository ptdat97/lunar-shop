<?php

namespace Modules\Content\Services;

use Modules\Content\Models\Banner;
use Modules\Content\Models\Lookbook;
use Modules\Content\Models\Page;

class ContentService
{
    /**
     * Get published pages.
     */
    public function pages(): array
    {
        return Page::published()->get()->all();
    }

    /**
     * Find a page by slug.
     */
    public function page(string $slug): ?Page
    {
        return Page::published()->where('slug', $slug)->first();
    }

    /**
     * Get active banners sorted by sort order.
     */
    public function banners(): array
    {
        return Banner::active()->get()->all();
    }

    /**
     * Published lookbooks, newest first (for the index page).
     */
    public function lookbooks(): array
    {
        return Lookbook::published()->latest()->get()->all();
    }

    /**
     * Find a published lookbook by slug, with its gallery images and products.
     */
    public function lookbook(string $slug): ?Lookbook
    {
        return Lookbook::published()
            ->with([
                'images',
                // Eager-load everything the shoppable hotspots + "shop the set"
                // button and product cards render (variants for add-to-cart, the
                // card relations, and which image each pin sits on).
                'items.product.variants.prices.currency',
                'items.product.thumbnail',
                'items.product.brand',
                'items.product.defaultUrl',
                'items.image',
            ])
            ->where('slug', $slug)
            ->first();
    }
}