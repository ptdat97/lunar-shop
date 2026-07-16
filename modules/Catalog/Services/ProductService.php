<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Lunar\Models\Product;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductVariant;
use Modules\Assets\Services\MediaUrl;
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
                // values.media: swatch images for the colour picker (§optionGroups).
                // Disabled variants are excluded from the storefront everywhere.
                'variants' => fn ($q) => $q->where('status', 'published')
                    ->with(['values.option', 'values.media', 'prices.currency', 'images']),
                'thumbnail', 'brand', 'collections.defaultUrl', 'defaultUrl', 'media',
            ])
            ->first();
    }

    /**
     * Option groups derived from the loaded variants, in a stable first-seen
     * order, for the SSR option buttons. Keys are the translated option names;
     * each group carries its handle + the option's configured display_type
     * (text | color | image — Product Options admin page) plus per-value
     * swatch data, so swatch groups render as colour/image swatches instead
     * of text buttons:
     *
     *   ['Color' => ['handle' => 'color', 'display_type' => 'color', 'values' => [
     *       ['label' => 'Black', 'color' => '#1a1a1a', 'image' => null], ...
     *   ]]]
     *
     * @return array<string, array{handle: ?string, display_type: string, values: list<array{label: string, color: ?string, image: ?string}>}>
     */
    public function optionGroups(Product $product): array
    {
        $groups = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->values as $value) {
                $option = $value->option;
                $optName = $option?->translate('name') ?? ($option?->name ?? 'Option');
                $valName = $value->translate('name') ?? $value->name;

                $groups[$optName] ??= [
                    'handle' => $option?->handle,
                    'display_type' => $option?->display_type ?? 'text',
                    'values' => [],
                ];

                $exists = collect($groups[$optName]['values'])
                    ->contains(fn ($existing) => $existing['label'] === $valName);

                if (! $exists) {
                    $groups[$optName]['values'][] = [
                        'label' => $valName,
                        'color' => $value->swatch_color,
                        'image' => $this->swatchImageUrl($value),
                    ];
                }
            }
        }

        return $groups;
    }

    /**
     * The selected variant's option values keyed by (translated) option name —
     * the same keys optionGroups() emits — for the SSR "active" button state.
     *
     * @return array<string, string>
     */
    public function selectedOptionValues(?ProductVariant $variant): array
    {
        return collect($variant?->values ?? [])
            ->mapWithKeys(fn ($value) => [
                ($value->option?->translate('name') ?? $value->option?->name ?? 'Option') => ($value->translate('name') ?? $value->name),
            ])
            ->all();
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
    public function resolveSelectedVariant(Product $product, array $query): ?ProductVariant
    {
        $first = $product->variants->first();

        $queryOptions = collect($query)
            ->mapWithKeys(fn ($v, $k) => [strtolower((string) $k) => strtolower((string) $v)]);

        if ($queryOptions->isEmpty()) {
            return $first;
        }

        return $product->variants->first(function ($variant) use ($queryOptions) {
            $variantOptions = $variant->values->mapWithKeys(fn ($value) => [
                strtolower((string) ($value->option?->translate('name') ?? $value->option?->name ?? '')) => strtolower((string) ($value->translate('name') ?? $value->name)),
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
     * @return Collection<int, Product>
     */
    public function bySlugs(array $slugs, int $limit = 12): Collection
    {
        $slugs = array_slice(array_values(array_unique(array_filter($slugs))), 0, $limit);

        if (empty($slugs)) {
            return collect();
        }

        $products = Product::query()
            ->where('status', 'published')
            ->whereHas('urls', fn ($u) => $u->whereIn('slug', $slugs))
            ->with(['variants' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
            ->get();

        // Re-order to match the requested slug order (DB returns arbitrary order).
        $order = array_flip($slugs);

        return $products
            ->sortBy(fn (Product $p) => $order[$p->defaultUrl?->slug] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Published products for a list of ids, returned IN THE GIVEN ORDER.
     *
     * The server-side "recently viewed" list stores ids rather than slugs — a
     * slug can be edited in the admin, an id cannot. One query; unknown or
     * unpublished ids are dropped.
     *
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    public function byIds(array $ids, int $limit = 12): Collection
    {
        $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, $limit);

        if (empty($ids)) {
            return collect();
        }

        $products = Product::query()
            ->where('status', 'published')
            ->whereIn('id', $ids)
            ->with(['variants' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
            ->get();

        $order = array_flip($ids);

        return $products
            ->sortBy(fn (Product $p) => $order[$p->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Resolve a swatch image URL using the MediaUrl service (self-healing
     * conversions, never returns the original full-size image).
     */
    protected function swatchImageUrl(ProductOptionValue $value): ?string
    {
        $media = $value->getFirstMedia(ProductOptionValue::SWATCH_COLLECTION);

        return $media ? app(MediaUrl::class)->conversion($media, 'small') : null;
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
            ->with(['variants' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media']);

        if ($collectionIds->isNotEmpty()) {
            $query->whereHas('collections', fn ($c) => $c->whereKey($collectionIds));
        }

        // Collection-similarity fallback used by RecommendationService's
        // CollectionStrategy when curated associations don't fill the slots.
        return $query->latest('id')->limit($limit)->get();
    }
}
