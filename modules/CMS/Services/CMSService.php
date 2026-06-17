<?php

namespace Modules\CMS\Services;

use Modules\CMS\Models\Banner;
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
}