<?php

namespace Modules\Content\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Lookbook;
use Modules\Content\Models\Page;

class ContentService
{
    /**
     * Published pages, newest first.
     *
     * Paginated: an unbounded list endpoint grows with the CMS, and every other
     * list in the API answers `?page=` / `?per_page=`.
     *
     * @return LengthAwarePaginator<int, Page>
     */
    public function pages(int $perPage = 20, int $page = 1)
    {
        return Page::published()
            ->latest('id')
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Find a page by slug.
     */
    public function page(string $slug): ?Page
    {
        return Page::published()->where('slug', $slug)->first();
    }

    /**
     * Every published page's slug + last-modified, for the sitemap.
     *
     * Unpaginated on purpose — a sitemap needs the whole set. It lives here, not
     * in Catalog, because "which pages are public" is Content's rule to state
     * (§10: cross-module traffic goes through the owning service, never its
     * models). Catalog used to run `Page::where('published', true)` itself, a
     * second spelling of `Page::published()` free to drift from it.
     *
     * @return Collection<int, Page>
     */
    public function publishedPageUrls(): Collection
    {
        return Page::published()->get(['slug', 'updated_at']);
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
                'items.product.variants.prices',
                'items.product.thumbnail',
                'items.product.brand',
                'items.product.defaultUrl',
                'items.product.media', // card hover (second) image
                'items.image',
            ])
            ->where('slug', $slug)
            ->first();
    }
}
