<?php

namespace Modules\Catalog\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Lunar\Models\Product;
use Modules\Catalog\Http\Resources\ReviewResource;
use Modules\Catalog\Services\ReviewService;
use Modules\Core\Support\ApiPagination;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    /**
     * GET /api/v1/products/{product}/reviews
     *
     * `meta` carries the pagination counters used everywhere else in the API;
     * the rating roll-up moved to `summary` when this endpoint was paginated.
     */
    public function index(Request $request, Product $product): AnonymousResourceCollection
    {
        $reviews = $this->reviews->forProduct(
            $product->id,
            perPage: ApiPagination::perPage($request, default: 20, max: 50),
            page: ApiPagination::page($request),
        );

        return ReviewResource::collection($reviews->getCollection())
            ->additional([
                'summary' => $this->reviews->summaryFor($product->id),
                'meta' => ApiPagination::meta($reviews),
            ]);
    }

    /** POST /api/v1/products/{product}/reviews */
    public function store(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'author' => ['required', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->reviews->add($product->id, $data['author'], $data['rating'], $data['body'] ?? null);

        return response()->json(['data' => $this->reviews->summaryFor($product->id)], 201);
    }
}
