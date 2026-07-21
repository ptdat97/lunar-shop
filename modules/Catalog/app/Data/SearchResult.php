<?php

namespace Modules\Catalog\Data;

use Illuminate\Support\Collection;

/**
 * Normalised search output — one shape for every driver.
 */
class SearchResult
{
    public function __construct(
        /** @var Collection<int, mixed> matched models/items */
        public Collection $items,
        public int $total,
        public int $page,
        public int $perPage,
        /** @var array<string, mixed> facet => buckets/counts */
        public array $facets = [],
    ) {}

    public function lastPage(): int
    {
        return (int) max(1, ceil($this->total / max(1, $this->perPage)));
    }
}
