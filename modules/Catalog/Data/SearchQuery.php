<?php

namespace Modules\Catalog\Data;

/**
 * Normalised search input — independent of any engine.
 */
class SearchQuery
{
    public function __construct(
        public string $term = '',
        /** @var array<string, mixed> filter => value(s): size, color, price, brand, material, availability */
        public array $filters = [],
        public ?string $sort = null,
        public int $page = 1,
        public int $perPage = 24,
        /** Optional collection/category scope (slug or id). */
        public ?string $scope = null,
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            term: (string) $request->string('q', ''),
            filters: (array) $request->input('filters', []),
            sort: $request->input('sort'),
            page: max(1, (int) $request->input('page', 1)),
            perPage: min(60, max(1, (int) $request->input('per_page', 24))),
            scope: $request->input('scope'),
        );
    }
}
