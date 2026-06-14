<?php

namespace Modules\Collection\Services;

use Lunar\Models\Collection;

/**
 * Shared collection read-logic. Wraps Lunar's Collection model (inherited,
 * not reimplemented).
 */
class CollectionService
{
    public function findBySlug(string $slug): ?Collection
    {
        return Collection::query()
            ->whereHas('urls', fn ($u) => $u->where('slug', $slug))
            ->first();
    }

    /**
     * Published products in a collection, paginated.
     */
    public function products(Collection $collection, int $page = 1, int $perPage = 24)
    {
        return $collection->products()
            ->where('status', 'published')
            ->with(['variants', 'thumbnail', 'brand'])
            ->paginate(perPage: $perPage, page: $page);
    }
}
