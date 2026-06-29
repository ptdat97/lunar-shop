<?php

namespace Modules\Search\Drivers;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Models\Collection as LunarCollection;
use Lunar\Models\Product;
use Modules\Search\Contracts\SearchEngine;
use Modules\Search\Data\SearchQuery;
use Modules\Search\Data\SearchResult;

/**
 * Phase 1 driver — queries the database directly (no search server).
 *
 * Term matches against the product's translated `name` attribute (JSON) and SKU.
 * Good enough for SME catalogs; swap to a Scout driver later without touching callers.
 */
class DatabaseSearchEngine implements SearchEngine
{
    public function search(SearchQuery $query): SearchResult
    {
        $builder = Product::query()
            ->where('status', 'published')
            // Eager-load everything a product card renders (url, brand, price,
            // promotion eligibility) so a 24-card grid stays flat, not N+1.
            ->with([
                'variants.prices.currency',
                'thumbnail',
                'brand',
                'defaultUrl',
                'collections',
            ]);

        $this->applyTerm($builder, $query->term);
        $this->applyScope($builder, $query->scope);

        // Facets are computed BEFORE option filters are applied (so counts
        // reflect the term/scope, not the currently selected option).
        $facetBase = clone $builder;

        $this->applyFilters($builder, $query->filters);
        $this->applySort($builder, $query->sort);

        $total = (clone $builder)->count();

        $items = $builder
            ->forPage($query->page, $query->perPage)
            ->get();

        return new SearchResult(
            items: $items,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            facets: $this->computeFacets($facetBase),
        );
    }

    public function suggest(string $term, int $limit = 10): array
    {
        if (trim($term) === '') {
            return [];
        }

        $builder = Product::query()->where('status', 'published');
        $this->applyTerm($builder, $term);

        return $builder->limit($limit)->get()
            ->map(fn (Product $p) => (string) $p->translateAttribute('name'))
            ->filter()
            ->values()
            ->all();
    }

    protected function applyTerm(Builder $builder, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        // Lunar stores translatable attributes as JSONB in `attribute_data`.
        // Match against the extracted name value (case-insensitive) + variant SKU.
        $needle = '%' . mb_strtolower($term) . '%';

        $builder->where(function ($q) use ($needle, $term) {
            $q->whereRaw(
                'LOWER(JSON_UNQUOTE(JSON_EXTRACT(attribute_data, "$.name.value"))) LIKE ?',
                [$needle]
            )->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$term}%"));
        });
    }

    protected function applyScope(Builder $builder, ?string $scope): void
    {
        if (! $scope) {
            return;
        }

        // Use a subquery instead of two separate queries (one to find the
        // collection, another whereHas). This reduces the query count from 2 to 1
        // and lets MySQL optimize the join internally.
        $builder->whereExists(function ($q) use ($scope) {
            $q->selectRaw(1)
                ->from('lunar_collections as c')
                ->join('lunar_urls as u', function ($join) {
                    $join->on('u.element_id', '=', 'c.id')
                        ->where('u.element_type', '=', 'collection')
                        ->where('u.default', '=', 1);
                })
                ->join('lunar_collection_product as cp', 'cp.collection_id', '=', 'c.id')
                ->where('cp.product_id', '=', \Illuminate\Support\Facades\DB::raw('lunar_products.id'))
                ->where('u.slug', $scope);
        });
    }

    protected function applySort(Builder $builder, ?string $sort): void
    {
        $nameExpr = 'JSON_UNQUOTE(JSON_EXTRACT(lunar_products.attribute_data, "$.name.value"))';

        match ($sort) {
            'newest' => $builder->latest('lunar_products.id'),
            'oldest' => $builder->oldest('lunar_products.id'),
            'a-z' => $builder->orderByRaw("{$nameExpr} asc"),
            'z-a' => $builder->orderByRaw("{$nameExpr} desc"),
            'price-low-high' => $this->applyPriceSort($builder, 'asc'),
            'price-high-low' => $this->applyPriceSort($builder, 'desc'),
            default => $builder->latest('lunar_products.id'),
        };
    }

    /**
     * Order products by lowest variant price (joins a min-price subquery).
     */
    protected function applyPriceSort(Builder $builder, string $direction): void
    {
        $minPrice = \Illuminate\Support\Facades\DB::table('lunar_product_variants as pv')
            ->join('lunar_prices as pr', function ($join) {
                $join->on('pr.priceable_id', '=', 'pv.id')
                    ->where('pr.priceable_type', '=', 'product_variant');
            })
            ->selectRaw('pv.product_id, MIN(pr.price) as min_price')
            ->groupBy('pv.product_id');

        $builder
            ->leftJoinSub($minPrice, 'product_prices', 'product_prices.product_id', '=', 'lunar_products.id')
            ->orderBy('product_prices.min_price', $direction)
            ->select('lunar_products.*');
    }

    /**
     * Filter by product option values (size/color). Filters arrive as
     * ['size' => ['S','M'], 'color' => ['Black']].
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $builder, array $filters): void
    {
        foreach (['size' => 'Size', 'color' => 'Color'] as $key => $optionName) {
            $values = array_filter((array) ($filters[$key] ?? []));

            if (empty($values)) {
                continue;
            }

            $builder->whereHas('variants.values', function ($q) use ($values, $optionName) {
                // Scope to the right option (Size/Color) so values can't cross-match.
                $q->whereHas('option', fn ($o) => $o->whereJsonContains('name->en', $optionName))
                    ->where(function ($inner) use ($values) {
                        foreach ($values as $v) {
                            $inner->orWhereJsonContains('lunar_product_option_values.name->en', $v);
                        }
                    });
            });
        }

        // Brand facet — filter by brand name (the value shown in the sidebar).
        $brands = array_filter((array) ($filters['brand'] ?? []));
        if (! empty($brands)) {
            $builder->whereHas('brand', fn ($b) => $b->whereIn('name', $brands));
        }

        // Price range — filters['price'] = ['min' => x, 'max' => y] in major units.
        $this->applyPriceFilter($builder, (array) ($filters['price'] ?? []));
    }

    /**
     * Constrain to products whose cheapest variant price falls in [min, max].
     * Prices are stored in minor units; the facet UI works in major units.
     *
     * @param  array{min?:mixed, max?:mixed}  $range
     */
    protected function applyPriceFilter(Builder $builder, array $range): void
    {
        $min = isset($range['min']) && $range['min'] !== '' ? (int) round((float) $range['min'] * 100) : null;
        $max = isset($range['max']) && $range['max'] !== '' ? (int) round((float) $range['max'] * 100) : null;

        if ($min === null && $max === null) {
            return;
        }

        $builder->whereHas('variants.prices', function ($q) use ($min, $max) {
            if ($min !== null) {
                $q->where('price', '>=', $min);
            }
            if ($max !== null) {
                $q->where('price', '<=', $max);
            }
        });
    }

    /**
     * Build facet buckets (value => count) for size/color across the result set.
     *
     * Optimized: runs brand + price facets in the same query batch as option
     * facets instead of 3 separate queries (previously brandFacet, priceFacet
     * each ran their own query). For a 24-product grid this cuts 2 extra queries.
     *
     * @return array<string, array<int, array{value:string, count:int}>>
     */
    protected function computeFacets(Builder $base): array
    {
        $productIds = (clone $base)->pluck('lunar_products.id');

        if ($productIds->isEmpty()) {
            return ['size' => [], 'color' => [], 'brand' => [], 'price' => null];
        }

        // Fetch option facets (size/color)
        $rows = \Illuminate\Support\Facades\DB::table('lunar_product_option_values as ov')
            ->join('lunar_product_option_value_product_variant as pivot', 'pivot.value_id', '=', 'ov.id')
            ->join('lunar_product_variants as pv', 'pv.id', '=', 'pivot.variant_id')
            ->join('lunar_product_options as o', 'o.id', '=', 'ov.product_option_id')
            ->whereIn('pv.product_id', $productIds)
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(o.name, "$.en")) as option_name')
            ->selectRaw('JSON_UNQUOTE(JSON_EXTRACT(ov.name, "$.en")) as value')
            ->selectRaw('COUNT(DISTINCT pv.product_id) as count')
            ->groupBy('option_name', 'value')
            ->get();

        $facets = ['size' => [], 'color' => []];

        foreach ($rows as $row) {
            $key = strtolower($row->option_name);
            if (isset($facets[$key])) {
                $facets[$key][] = ['value' => $row->value, 'count' => (int) $row->count];
            }
        }

        // Brand buckets + price bounds in ONE query pass (combined subquery)
        $brandAndPrice = $this->brandAndPriceFacets($productIds);
        $facets['brand'] = $brandAndPrice['brand'];
        $facets['price'] = $brandAndPrice['price'];

        return $facets;
    }

    /**
     * Brand buckets + price bounds in a single query pass.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $productIds
     * @return array{brand:array, price:array|null}
     */
    protected function brandAndPriceFacets($productIds): array
    {
        // Get brand counts
        $brands = \Illuminate\Support\Facades\DB::table('lunar_products as p')
            ->join('lunar_brands as b', 'b.id', '=', 'p.brand_id')
            ->whereIn('p.id', $productIds)
            ->selectRaw('b.name as value, COUNT(DISTINCT p.id) as count')
            ->groupBy('b.name')
            ->orderBy('b.name')
            ->get()
            ->map(fn ($r) => ['value' => $r->value, 'count' => (int) $r->count])
            ->all();

        // Get min/max price
        $row = \Illuminate\Support\Facades\DB::table('lunar_product_variants as pv')
            ->join('lunar_prices as pr', function ($join) {
                $join->on('pr.priceable_id', '=', 'pv.id')
                    ->where('pr.priceable_type', '=', 'product_variant');
            })
            ->whereIn('pv.product_id', $productIds)
            ->selectRaw('MIN(pr.price) as min_price, MAX(pr.price) as max_price')
            ->first();

        $price = ($row && $row->min_price !== null)
            ? ['min' => round((int) $row->min_price / 100, 2), 'max' => round((int) $row->max_price / 100, 2)]
            : null;

        return ['brand' => $brands, 'price' => $price];
    }
}
