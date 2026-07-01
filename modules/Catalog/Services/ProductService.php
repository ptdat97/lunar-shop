<?php

namespace Modules\Catalog\Services;

use Lunar\Models\Product;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Data\SearchResult;

/**
 * Single source of product read-logic. Both the Storefront controller and the
 * API controller call this — no duplicated business logic.
 */
class ProductService
{
    public function __construct(
        protected SearchEngine $search,
    ) {}

    /**
     * Paginated/filtered product listing (delegates to the search abstraction).
     */
    public function list(SearchQuery $query): SearchResult
    {
        return $this->search->search($query);
    }

    /**
     * Resolve a single published product by its URL slug.
     *
     * Optimized: joins urls directly instead of using whereHas (subquery),
     * and eager-loads everything needed for the product page: variants with
     * their option values + prices, media gallery, brand, collections,
     * and SEO URLs — all in one query.
     */
    public function findBySlug(string $slug): ?Product
    {
        return Product::query()
            ->select('lunar_products.*')
            ->where('lunar_products.status', 'published')
            ->join('lunar_urls', function ($join) {
                $join->on('lunar_urls.element_id', '=', 'lunar_products.id')
                    ->where('lunar_urls.element_type', '=', 'product')
                    ->where('lunar_urls.default', '=', 1);
            })
            ->where('lunar_urls.slug', $slug)
            ->with([
                'variants' => fn ($q) => $q->with(['values.option', 'prices.currency']),
                'thumbnail', 'brand', 'collections.defaultUrl', 'defaultUrl', 'media',
            ])
            ->first();
    }

    /**
     * Resolve which variant a deep-link query selects (e.g. ?color=red&size=m),
     * for SSR (no-JS + crawlers). Keys are the lowercased option name; values
     * match option values case-insensitively. A variant qualifies only if it
     * carries every queried option and each value matches. Falls back to the
     * first variant when the query is empty or matches nothing.
     *
     * The storefront JS (enhance/product-variant.js) keeps this URL in sync as
     * options change, so an SSR render and the JS state agree on the variant.
     *
     * @param  array<string, mixed>  $query  request()->query()
     */
    public function resolveSelectedVariant(Product $product, array $query): ?\Lunar\Models\ProductVariant
    {
        $first = $product->variants->first();

        $queryOptions = collect($query)
            ->mapWithKeys(fn ($v, $k) => [strtolower((string) $k) => strtolower((string) $v)]);

        if ($queryOptions->isEmpty()) {
            return $first;
        }

        return $product->variants->first(function ($variant) use ($queryOptions) {
            $variantOptions = $variant->values->mapWithKeys(fn ($value) => [
                strtolower((string) ($value->option?->translate('name') ?? $value->option?->name ?? ''))
                    => strtolower((string) ($value->translate('name') ?? $value->name)),
            ]);

            // Every queried option must be present on this variant with a matching value.
            return $queryOptions->every(
                fn ($val, $key) => $variantOptions->has($key) && $variantOptions->get($key) === $val,
            );
        }) ?? $first;
    }

    /**
     * Published products for a list of URL slugs, returned IN THE GIVEN ORDER
     * (used by "recently viewed" — the client stores slugs newest-first). One
     * query; unknown/unpublished slugs are simply dropped.
     *
     * @param  array<int, string>  $slugs
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function bySlugs(array $slugs, int $limit = 12): \Illuminate\Support\Collection
    {
        $slugs = array_slice(array_values(array_unique(array_filter($slugs))), 0, $limit);

        if (empty($slugs)) {
            return collect();
        }

        $products = Product::query()
            ->where('status', 'published')
            ->whereHas('urls', fn ($u) => $u->whereIn('slug', $slugs))
            ->with(['variants.prices.currency', 'thumbnail', 'brand', 'defaultUrl', 'collections'])
            ->get();

        // Re-order to match the requested slug order (DB returns arbitrary order).
        $order = array_flip($slugs);

        return $products
            ->sortBy(fn (Product $p) => $order[$p->defaultUrl?->slug] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Products related to the given one (same collections), excluding itself.
     */
    public function related(Product $product, int $limit = 8)
    {
        $collectionIds = $product->collections->pluck('id');

        $query = Product::query()
            ->where('status', 'published')
            ->where('id', '!=', $product->id)
            ->with(['variants.prices.currency', 'thumbnail', 'brand', 'defaultUrl', 'collections']);

        if ($collectionIds->isNotEmpty()) {
            $query->whereHas('collections', fn ($c) => $c->whereKey($collectionIds));
        }

        // Collection-similarity fallback used by RecommendationService's
        // CollectionStrategy when curated associations don't fill the slots.
        return $query->latest('id')->limit($limit)->get();
    }
}
