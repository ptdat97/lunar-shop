<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Lunar\Core\Enums\ProductOptionType;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Data\SearchResult;
use Modules\Catalog\Support\VariantAxes;

/**
 * Single source of product read-logic. Both the Storefront controller and the
 * API controller call this — no duplicated business logic.
 */
class ProductService
{
    public function __construct(
        protected SearchEngine $search,
        protected VariantAxes $axes,
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
     * and eager-loads everything needed for the product page: enabled variants
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
                // Disabled variants are excluded from the storefront everywhere.
                // The picker is derived from the product's shared options, so
                // both sides of that (the variant's values, and the product's
                // option list) are loaded here — VariantAxes joins them.
                'variants' => fn ($q) => $q->where('enabled', true)
                    ->with(['prices.currency', 'values'])
                    ->chaperone(),
                'productOptions.values',
                'thumbnail', 'brand', 'collections.defaultUrl', 'defaultUrl', 'media',
            ])
            ->first();
    }

    /**
     * Resolve a legacy/alias URL slug to its canonical product slug.
     *
     * Lunar keeps every old slug in `lunar_urls` (default = 0) so past links
     * keep working after a rename — they must 301 to the canonical URL, not
     * 404. Only published products resolve, mirroring findBySlug().
     *
     * Returns null when the slug does not belong to a published product, or
     * when it is already the canonical slug.
     */
    public function canonicalSlugFor(string $slug): ?string
    {
        $product = Product::query()
            ->select('lunar_products.*')
            ->where('lunar_products.status', 'published')
            ->join('lunar_urls', function ($join) {
                $join->on('lunar_urls.element_id', '=', 'lunar_products.id')
                    ->where('lunar_urls.element_type', '=', 'product');
            })
            ->where('lunar_urls.slug', $slug)
            ->with('defaultUrl')
            ->first();

        if ($product === null || $product->defaultUrl?->slug === $slug) {
            return null;
        }

        return $product->defaultUrl->slug;
    }

    /**
     * Option groups for the SSR option buttons, in axis order. Keys are the
     * localised option names; each group carries the per-value swatch data
     * (a colour or an image when the option says so, else a plain label):
     *
     *   ['Color' => ['handle' => 'color', 'display_type' => 'image'|'text', 'values' => [
     *       ['label' => 'Black', 'color' => null, 'image' => '…'], ...
     *   ]]]
     *
     * @return array<string, array{handle: ?string, display_type: string, values: list<array{label: string, color: ?string, image: ?string}>}>
     */
    public function optionGroups(Product $product): array
    {
        $groups = [];

        foreach ($this->axes->axes($product) as $axis) {
            $option = $axis['option'];
            $optName = (string) $option->translate('name') ?: 'Option';
            $displayType = $this->displayType($option->type);

            $groups[$optName] = [
                'handle' => $option->handle ?: Str::slug($optName),
                'display_type' => $displayType,
                'values' => $axis['values']
                    ->map(fn ($value) => [
                        'label' => (string) $value->translate('name'),
                        'color' => $displayType === 'color' ? data_get($value->meta, 'colour') : null,
                        'image' => $displayType === 'image' ? $this->swatchImageUrl(data_get($value->meta, 'image')) : null,
                    ])
                    ->filter(fn ($v) => $v['label'] !== '')
                    ->values()
                    ->all(),
            ];
        }

        return $groups;
    }

    /**
     * The selected variant's option values keyed by option name — the
     * same keys optionGroups() emits — for the SSR "active" button state.
     *
     * @return array<string, string>
     */
    public function selectedOptionValues(?ProductVariant $variant): array
    {
        if (! $variant) {
            return [];
        }

        $selected = [];

        foreach ($this->axes->pairs($variant->product, $variant) as $pair) {
            if ($pair['value'] !== '') {
                $selected[$pair['option']] = $pair['value'];
            }
        }

        return $selected;
    }

    /**
     * Resolve which variant a deep-link query selects (e.g. ?color=red&size=m),
     * for SSR (no-JS + crawlers). Keys are the lowercased variable name; values
     * match value labels case-insensitively. A variant qualifies only if it carries
     * every queried option and each value matches. Falls back to the first variant
     * when the query is empty or matches nothing.
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

        $selectedIndexes = [];

        foreach ($this->axes->axes($product) as $axis => $definition) {
            $optionName = (string) $definition['option']->translate('name') ?: 'Option';
            $rawValue = $queryOptions->get(strtolower($optionName))
                ?? $queryOptions->get(Str::slug($optionName));

            if ($rawValue === null) {
                continue;
            }

            $valueIndex = $definition['values']
                ->search(fn ($value) => strtolower((string) $value->translate('name')) === $rawValue);

            if ($valueIndex === false) {
                return $first;
            }

            $selectedIndexes[(int) $axis] = (int) $valueIndex;
        }

        if ($selectedIndexes === []) {
            return $first;
        }

        return $product->variants->first(function (ProductVariant $variant) use ($product, $selectedIndexes) {
            $indexes = $this->axes->indexes($product, $variant);

            foreach ($selectedIndexes as $axis => $valueIndex) {
                if ((int) ($indexes[$axis] ?? -1) !== $valueIndex) {
                    return false;
                }
            }

            return true;
        }) ?? $first;
    }

    /**
     * How an axis's values are rendered on the storefront: 'text' | 'color' |
     * 'image'.
     *
     * Lunar 2.0 owns this now — `ProductOption.type`, an indexed column backed
     * by `ProductOptionType`. It spells the two non-text cases differently
     * (en-GB `colour`, and `swatch` for an image), so this translates the
     * first-party vocabulary into the one the storefront payload and JS already
     * speak, rather than changing both.
     */
    protected function displayType(?string $optionType): string
    {
        return match (ProductOptionType::tryFrom((string) $optionType)) {
            ProductOptionType::Colour => 'color',
            ProductOptionType::Swatch => 'image',
            default => 'text',
        };
    }

    /**
     * Resolve a stored swatch image to a browser URL. The stored value is a
     * Media Library Asset id (picked via MediaPicker — modules/Assets), resolved
     * to the small `thumb` conversion so the storefront never loads the heavy
     * original. Blank / missing / deleted-from-library values yield null (the
     * blade skips them).
     */
    protected function swatchImageUrl($image): ?string
    {
        return app(MediaLibraryService::class)->url($image, 'thumb');
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
            ->with(['variants' => fn ($q) => $q->where('enabled', true)->with(['prices', 'values'])->chaperone(),
                'productOptions.values', 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
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
            ->with(['variants' => fn ($q) => $q->where('enabled', true)->with(['prices', 'values'])->chaperone(),
                'productOptions.values', 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
            ->get();

        $order = array_flip($ids);

        return $products
            ->sortBy(fn (Product $p) => $order[$p->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Products related to the given one (same collections), excluding itself.
     */
    public function related(Product $product, int $limit = 8, bool $withCardRelations = true)
    {
        $collectionIds = $product->collections->pluck('id');

        $query = Product::query()
            ->where('status', 'published')
            ->where('id', '!=', $product->id);

        // Recommendation strategies need only ids/statuses to rank candidates;
        // the RecommendationService hydrates the final, de-duplicated list
        // once. Direct callers can still request ready-to-render card models.
        if ($withCardRelations) {
            $query->with([
                'variants' => fn ($q) => $q->where('enabled', true)->with(['prices', 'values'])->chaperone(),
                'productOptions.values',
                'thumbnail', 'brand', 'defaultUrl', 'collections', 'media',
            ]);
        }

        if ($collectionIds->isNotEmpty()) {
            $query->whereHas('collections', fn ($c) => $c->whereKey($collectionIds));
        }

        // Collection-similarity fallback used by RecommendationService's
        // CollectionStrategy when curated associations don't fill the slots.
        return $query->latest('id')->limit($limit)->get();
    }
}
