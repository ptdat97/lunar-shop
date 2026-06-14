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
            ->with(['variants', 'thumbnail']);

        $this->applyTerm($builder, $query->term);
        $this->applyScope($builder, $query->scope);
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
            facets: [], // filters/facets land when filter UI is built (P2)
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

        $collection = LunarCollection::query()
            ->whereHas('urls', fn ($u) => $u->where('slug', $scope))
            ->first();

        if ($collection) {
            $builder->whereHas('collections', fn ($c) => $c->where('collections.id', $collection->id));
        }
    }

    protected function applySort(Builder $builder, ?string $sort): void
    {
        match ($sort) {
            'newest' => $builder->latest('id'),
            'oldest' => $builder->oldest('id'),
            default => $builder->latest('id'),
        };
    }
}
