<?php

namespace Acme\Recommend\Strategies;

use Illuminate\Support\Collection;
use Lunar\Models\Product;
use Acme\Recommend\RecommendationStrategy;

/**
 * Curated recommendations from Lunar's ProductAssociation (cross-sell / up-sell /
 * alternate). Admins gather these in the product editor — we inherit Lunar's
 * association table, not reinvent it.
 *
 * Highest-priority strategy: hand-picked beats anything automated.
 */
class AssociationStrategy implements RecommendationStrategy
{
    /**
     * @param  list<string>  $types  association types to include, in order
     */
    public function __construct(
        protected array $types = ['cross-sell', 'up-sell', 'alternate'],
    ) {}

    public function for(Product $product, int $limit = 8): Collection
    {
        return $product->associations()
            ->whereIn('type', $this->types)
            ->with(['target.variants', 'target.thumbnail', 'target.brand'])
            ->get()
            ->map(fn ($association) => $association->target)
            ->filter(fn (?Product $target) => $target !== null && $target->status === 'published')
            ->take($limit)
            ->values();
    }
}
