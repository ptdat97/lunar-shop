<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Lunar\Models\Url;
use Modules\Content\Services\ContentService;

/**
 * Builds the storefront sitemap URL set. Lives in Catalog because it coordinates
 * catalog-wide SEO (the module's stated job). One service = one source, so the
 * controller stays thin and no Blade/controller touches models directly.
 *
 * CMS pages come from Content's service, not its models (§10).
 */
class SitemapService
{
    public function __construct(
        protected ContentService $content,
    ) {}

    /**
     * All indexable storefront URLs as { loc, lastmod?, changefreq, priority }.
     *
     * @return Collection<int, array{loc:string, lastmod:?string, changefreq:string, priority:string}>
     */
    public function urls(): Collection
    {
        $entries = collect();

        // Static landing pages.
        $entries->push($this->entry(route('storefront.home'), null, 'daily', '1.0'));
        $entries->push($this->entry(route('storefront.promotions'), null, 'daily', '0.6'));
        $entries->push($this->entry(route('storefront.lookbooks'), null, 'weekly', '0.5'));

        // Published products (via Lunar default Url, status published).
        $this->defaultUrlsFor(Product::class, 'status', 'published')
            ->each(fn (Url $url) => $entries->push($this->entry(
                route('storefront.product', $url->slug), $url->updated_at?->toAtomString(), 'weekly', '0.8',
            )));

        // Collections.
        $this->defaultUrlsFor(LunarCollection::class)
            ->each(fn (Url $url) => $entries->push($this->entry(
                route('storefront.collection', $url->slug), $url->updated_at?->toAtomString(), 'weekly', '0.7',
            )));

        // Published CMS pages — Content owns the "is it public?" rule.
        $this->content->publishedPageUrls()
            ->each(fn ($page) => $entries->push($this->entry(
                route('storefront.page', $page->slug), $page->updated_at?->toAtomString(), 'monthly', '0.4',
            )));

        return $entries->values();
    }

    /**
     * Default Urls for a Lunar element type, optionally filtered by a column on
     * the related element (e.g. only published products).
     *
     * `element_type` stores Lunar's morph alias ("product"/"collection"), not the
     * FQCN — so resolve it via getMorphClass() instead of matching the class name.
     *
     * @param  class-string  $elementType
     * @return Collection<int, Url>
     */
    protected function defaultUrlsFor(string $elementType, ?string $whereColumn = null, mixed $whereValue = null): Collection
    {
        $morphAlias = (new $elementType)->getMorphClass();

        return Url::query()
            ->where('element_type', $morphAlias)
            ->where('default', true)
            ->when($whereColumn, fn ($q) => $q->whereHasMorph(
                'element', [$morphAlias], fn ($eq) => $eq->where($whereColumn, $whereValue),
            ))
            ->with('element:id,updated_at')
            ->get();
    }

    /**
     * @return array{loc:string, lastmod:?string, changefreq:string, priority:string}
     */
    protected function entry(string $loc, ?string $lastmod, string $changefreq, string $priority): array
    {
        return compact('loc', 'lastmod', 'changefreq', 'priority');
    }
}
