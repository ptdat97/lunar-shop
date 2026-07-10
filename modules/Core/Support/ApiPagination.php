<?php

namespace Modules\Core\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * The one pagination contract for `/api/v1/*`.
 *
 * Request: `?page=`, `?per_page=` (the spelling the storefront and the Catalog
 * search have always used).
 * Response: `meta { page, per_page, last_page, total }`.
 *
 * Product, collection and search built that meta by hand, while endpoints that
 * returned a paginator straight through a JsonResource collection (orders) got
 * Laravel's default `{ data, links, meta { current_page, from, to, path, … } }`
 * instead — so a client could not share one parser. Others (`/customer/orders`)
 * emitted no meta at all, and several accepted no page parameters, leaving the
 * counters unreachable.
 */
class ApiPagination
{
    /** Hard ceiling so a client cannot ask for the whole table in one call. */
    public const MAX_PER_PAGE = 100;

    /**
     * @return array{page:int, per_page:int, last_page:int, total:int}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    /**
     * The same envelope for a search result, which carries its own totals rather
     * than an Eloquent paginator.
     *
     * @return array{page:int, per_page:int, last_page:int, total:int}
     */
    public static function metaFor(int $page, int $perPage, int $lastPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
            'total' => $total,
        ];
    }

    /** The requested page number, clamped to a sane minimum. */
    public static function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    /** The requested page size, clamped to `[1, $max]`. */
    public static function perPage(Request $request, int $default = 20, int $max = self::MAX_PER_PAGE): int
    {
        return min($max, max(1, (int) $request->input('per_page', $default)));
    }
}
