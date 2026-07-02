<?php

namespace Modules\Catalog\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Product;

/**
 * Combines recommendation strategies (in priority order) into a single, de-duped
 * list. Curated associations come first, the collection fallback fills the rest.
 * Results are cached per (product, context). Strategies come from
 * config('recommend.strategies').
 */
class RecommendationService
{
    /**
     * @param  list<\Modules\Catalog\Contracts\RecommendationStrategy>  $strategies
     */
    public function __construct(
        protected array $strategies,
        protected int $cacheTtl = 3600,
    ) {}

    /**
     * Recommendations for a product page.
     *
     * @param  list<int>  $exclude  extra product ids to drop (e.g. cart contents)
     * @return Collection<int, Product>
     */
    public function forProduct(Product $product, ?int $limit = null, array $exclude = []): Collection
    {
        // Null → use the admin-configured product-page limit (Catalog settings).
        $limit = $limit ?? $this->settingLimit('recommend.product_limit', 8);
        $key = "recommend:product:{$product->id}:{$limit}:" . md5(implode(',', $exclude));

        // Cache only the resolved product ids (cheap + avoids stale model state);
        // re-hydrate fresh models with the relations the storefront/API need.
        $ids = Cache::remember($key, $this->cacheTtl, function () use ($product, $limit, $exclude) {
            return $this->resolve($product, $limit, $exclude)->pluck('id')->all();
        });

        return $this->hydrate($ids);
    }

    /**
     * Recommendations for the cart: gather per-line recommendations, exclude
     * everything already in the cart. Not cached (cart is per-session).
     *
     * @param  Collection<int, Product>  $cartProducts  products currently in the cart
     * @return Collection<int, Product>
     */
    public function forCart(Collection $cartProducts, ?int $limit = null): Collection
    {
        // Null → use the admin-configured mini-cart limit (Catalog settings).
        $limit = $limit ?? $this->settingLimit('recommend.cart_limit', 6);

        if ($cartProducts->isEmpty()) {
            return collect();
        }

        $excludeIds = $cartProducts->pluck('id')->all();
        $picked = collect();

        foreach ($cartProducts as $product) {
            foreach ($this->resolve($product, $limit, $excludeIds) as $rec) {
                if (! $picked->contains('id', $rec->id)) {
                    $picked->push($rec);
                }
                if ($picked->count() >= $limit) {
                    break 2;
                }
            }
        }

        return $picked->values();
    }

    /**
     * Run strategies in order, merge + de-dupe, drop the source and excluded ids.
     *
     * @param  list<int>  $exclude
     * @return Collection<int, Product>
     */
    /**
     * A recommendation limit from Catalog settings (falls back to config, then
     * the given default). Guarded to a sane positive range.
     */
    protected function settingLimit(string $path, int $default): int
    {
        $value = (int) app(\Modules\Core\Support\Settings::class)->get($path, $default);

        return max(1, $value);
    }

    protected function resolve(Product $product, int $limit, array $exclude): Collection
    {
        $excludeIds = array_merge([$product->id], $exclude);
        $out = collect();

        foreach ($this->strategies as $strategy) {
            if ($out->count() >= $limit) {
                break;
            }

            foreach ($strategy->for($product, $limit) as $candidate) {
                if (in_array($candidate->id, $excludeIds, true) || $out->contains('id', $candidate->id)) {
                    continue;
                }
                $out->push($candidate);
                if ($out->count() >= $limit) {
                    break;
                }
            }
        }

        return $out->take($limit)->values();
    }

    /**
     * Re-hydrate products by id, preserving order, with storefront relations.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Product>
     */
    protected function hydrate(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            // Full product-card relation set (price + url + promotion eligibility)
            // so recommendation grids render flat, not N+1.
            ->with(['variants.prices.currency', 'thumbnail', 'brand', 'defaultUrl', 'collections', 'media'])
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $products->get($id))
            ->filter()
            ->values();
    }
}
