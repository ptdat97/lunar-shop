<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lunar\Models\Product;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Data\SearchResult;
use Modules\Catalog\Models\ProductSku;

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
     * and eager-loads everything needed for the product page: published SKUs
     * with their prices, media gallery, brand, collections, and SEO URLs — all
     * in one query.
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
                // Disabled SKUs are excluded from the storefront everywhere. The
                // colour/size picker is derived from the product's `variables`
                // blob (§optionGroups), so no option/value relations to load.
                'skus' => fn ($q) => $q->where('status', 'published')
                    ->with(['prices.currency']),
                'thumbnail', 'brand', 'collections.defaultUrl', 'defaultUrl', 'media',
            ])
            ->first();
    }

    /**
     * Option groups derived straight from the product's flexible `variables`
     * definition, in definition order, for the SSR option buttons. Keys are the
     * localised variable names; each group carries the per-value swatch data
     * (an image when the variable is flagged `isImage`, else a plain label):
     *
     *   ['Color' => ['handle' => 'color', 'display_type' => 'image'|'text', 'values' => [
     *       ['label' => 'Black', 'color' => null, 'image' => '…'], ...
     *   ]]]
     *
     * @return array<string, array{handle: ?string, display_type: string, values: list<array{label: string, color: ?string, image: ?string}>}>
     */
    public function optionGroups(Product $product): array
    {
        $locale = app()->getLocale();
        $groups = [];

        foreach ($product->variables ?? [] as $variable) {
            $optName = $this->localised($variable['name'] ?? [], $locale) ?: 'Option';
            $displayType = $this->displayType($variable);

            $groups[$optName] = [
                'handle' => Str::slug($optName),
                'display_type' => $displayType,
                'values' => collect($variable['values'] ?? [])
                    ->map(fn ($value) => [
                        'label' => $this->localised($value['name'] ?? [], $locale),
                        'color' => $displayType === 'color' ? ($value['color'] ?? null) : null,
                        'image' => $displayType === 'image' ? $this->swatchImageUrl($value['image'] ?? null) : null,
                    ])
                    ->filter(fn ($v) => $v['label'] !== '')
                    ->values()
                    ->all(),
            ];
        }

        return $groups;
    }

    /**
     * The selected SKU's option values keyed by (localised) variable name — the
     * same keys optionGroups() emits — for the SSR "active" button state.
     *
     * @return array<string, string>
     */
    public function selectedOptionValues(?ProductSku $sku): array
    {
        if (! $sku) {
            return [];
        }

        $locale = app()->getLocale();
        $variables = $sku->product->variables ?? [];
        $indexes = $sku->variants ?? [];
        $selected = [];

        foreach ($variables as $i => $variable) {
            $valueIndex = $indexes[$i] ?? null;
            if ($valueIndex === null) {
                continue;
            }

            $optName = $this->localised($variable['name'] ?? [], $locale) ?: 'Option';
            $valName = $this->localised($variable['values'][$valueIndex]['name'] ?? [], $locale);

            if ($valName !== '') {
                $selected[$optName] = $valName;
            }
        }

        return $selected;
    }

    /**
     * Resolve which SKU a deep-link query selects (e.g. ?color=red&size=m),
     * for SSR (no-JS + crawlers). Keys are the lowercased variable name; values
     * match value labels case-insensitively. A SKU qualifies only if it carries
     * every queried option and each value matches. Falls back to the first SKU
     * when the query is empty or matches nothing.
     *
     * The storefront JS (enhance/product-variant.js) keeps this URL in sync as
     * options change, so an SSR render and the JS state agree on the SKU.
     *
     * @param  array<string, mixed>  $query  request()->query()
     */
    public function resolveSelectedVariant(Product $product, array $query): ?ProductSku
    {
        $first = $product->skus->first();

        $queryOptions = collect($query)
            ->mapWithKeys(fn ($v, $k) => [strtolower((string) $k) => strtolower((string) $v)]);

        if ($queryOptions->isEmpty()) {
            return $first;
        }

        return $product->skus->first(function (ProductSku $sku) use ($queryOptions) {
            $skuOptions = collect($this->selectedOptionValues($sku))
                ->mapWithKeys(fn ($val, $key) => [strtolower((string) $key) => strtolower((string) $val)]);

            // Every queried option must be present on this SKU with a matching value.
            return $queryOptions->every(
                fn ($val, $key) => $skuOptions->has($key) && $skuOptions->get($key) === $val,
            );
        }) ?? $first;
    }

    /**
     * Pull a localised string out of a {locale: string} map, falling back to
     * the first available translation so a missing locale never blanks a label.
     *
     * @param  mixed  $names
     */
    protected function localised($names, string $locale): string
    {
        if (! is_array($names)) {
            return (string) $names;
        }

        return (string) ($names[$locale] ?? reset($names) ?: '');
    }

    /**
     * How an axis's values are rendered on the storefront: 'text' | 'color' |
     * 'image'. Reads the explicit `display_type` set in the variant builder,
     * falling back to the legacy boolean `isImage` flag (old data / imports).
     *
     * @param  array<string, mixed>  $variable
     */
    protected function displayType(array $variable): string
    {
        $type = $variable['display_type'] ?? null;

        if (in_array($type, ['text', 'color', 'image'], true)) {
            return $type;
        }

        return ! empty($variable['isImage']) ? 'image' : 'text';
    }

    /**
     * Resolve a stored swatch image (a path on the public `media` disk, e.g.
     * "variant-swatches/x.jpg") to a browser URL. Absolute URLs are returned
     * untouched; blanks yield null.
     */
    protected function swatchImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        return Storage::disk('media')->url($path);
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
            ->with(['skus' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
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
            ->with(['skus' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
            ->get();

        $order = array_flip($ids);

        return $products
            ->sortBy(fn (Product $p) => $order[$p->id] ?? PHP_INT_MAX)
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
            ->with(['skus' => fn ($q) => $q->where('status', 'published')->with('prices'), 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media']);

        if ($collectionIds->isNotEmpty()) {
            $query->whereHas('collections', fn ($c) => $c->whereKey($collectionIds));
        }

        // Collection-similarity fallback used by RecommendationService's
        // CollectionStrategy when curated associations don't fill the slots.
        return $query->latest('id')->limit($limit)->get();
    }
}
