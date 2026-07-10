<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\Customer;
use Modules\Core\Support\ApiPagination;
use Modules\Order\Services\OrderService;

/**
 * A page of a customer's orders, in the API's one envelope:
 * `{ data: OrderResource[], meta: {page, per_page, last_page, total} }`.
 *
 * Two endpoints serve this exact payload — `GET /api/v1/orders` and the
 * storefront's `GET /api/v1/customer/orders` — and both had their own copy of
 * the same thirty lines, including the empty-customer branch. One shape, one
 * place to change it.
 */
class CustomerOrderPage
{
    /**
     * Build the response for a customer, or the empty envelope when the user has
     * no customer record yet (a guest, or someone who never checked out).
     */
    public static function for(Request $request, ?Customer $customer, OrderService $orders): JsonResponse
    {
        $page = ApiPagination::page($request);
        $perPage = ApiPagination::perPage($request);

        if (! $customer) {
            // The same envelope as the populated case, echoing the requested
            // page size rather than a hardcoded one.
            return response()->json([
                'data' => [],
                'meta' => ApiPagination::metaFor($page, $perPage, 1, 0),
            ]);
        }

        $paginator = $orders->customerOrders($customer->id, perPage: $perPage, page: $page);

        // Resolve the items ourselves: handing a paginator to `::collection()`
        // makes Laravel emit its own `{links, meta{current_page, …}}`, a
        // different shape from the rest of the API.
        return response()->json([
            'data' => OrderResource::collection($paginator->getCollection())->resolve($request),
            'meta' => ApiPagination::meta($paginator),
        ]);
    }
}
