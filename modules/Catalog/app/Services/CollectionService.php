<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Collection;

/**
 * Shared collection read-logic. Wraps Lunar's Collection model (inherited,
 * not reimplemented).
 */
class CollectionService
{
    /**
     * Resolve a collection by its URL slug.
     *
     * Optimized: uses a direct JOIN instead of whereHas (subquery) to avoid
     * an extra query execution. The new composite index on (slug, element_type,
     * default) makes this join instant.
     */
    public function findBySlug(string $slug): ?Collection
    {
        return Collection::query()
            ->select('lunar_collections.*')
            ->join('lunar_urls', function ($join) {
                $join->on('lunar_urls.element_id', '=', 'lunar_collections.id')
                    ->where('lunar_urls.element_type', '=', 'collection')
                    ->where('lunar_urls.default', '=', 1);
            })
            ->where('lunar_urls.slug', $slug)
            ->first();
    }

    /**
     * Published products in a collection, paginated + sorted.
     */
    public function products(Collection $collection, int $page = 1, int $perPage = 24, ?string $sort = null)
    {
        $query = $collection->products()
            ->where('status', 'published')
            ->with(['skus', 'thumbnail', 'brand', 'media']); // media → card hover image

        // Lunar stores translatable name as JSONB; sort on the extracted value.
        $nameExpr = 'JSON_UNQUOTE(JSON_EXTRACT(lunar_products.attribute_data, "$.name.value"))';

        match ($sort) {
            'a-z' => $query->orderByRaw("{$nameExpr} asc"),
            'z-a' => $query->orderByRaw("{$nameExpr} desc"),
            'price-low-high' => $this->applyPriceSort($query, 'asc'),
            'price-high-low' => $this->applyPriceSort($query, 'desc'),
            default => $query->latest('lunar_products.id'),
        };

        return $query->paginate(perPage: $perPage, page: $page)->withQueryString();
    }

    /**
     * Order products by their lowest variant price, ascending/descending.
     * Joins a per-product min-price subquery (Lunar tables use the lunar_ prefix).
     */
    protected function applyPriceSort($query, string $direction)
    {
        $minPrice = DB::table('lunar_product_variants as pv')
            ->join('lunar_prices as pr', function ($join) {
                $join->on('pr.priceable_id', '=', 'pv.id')
                    ->where('pr.priceable_type', '=', 'product_variant');
            })
            ->selectRaw('pv.product_id, MIN(pr.price) as min_price')
            ->groupBy('pv.product_id');

        return $query
            ->leftJoinSub($minPrice, 'product_prices', 'product_prices.product_id', '=', 'lunar_products.id')
            ->orderBy('product_prices.min_price', $direction)
            ->select('lunar_products.*');
    }
}
