<?php

namespace Modules\CMS\Services;

use Modules\CMS\Models\Banner;
use Modules\CMS\Models\Lookbook;
use Modules\CMS\Models\Page;

class CMSService
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
            ->with(['images', 'items.product'])
            ->where('slug', $slug)
            ->first();
    }
}