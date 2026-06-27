<?php

namespace Modules\Review\Http\Controllers\Api\V1;

use Modules\Review\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Lunar\Models\Product;

class ReviewController extends Controller
{
    public function __construct(
        protected ReviewService $reviews,
    ) {}

    /** GET /api/v1/products/{product}/reviews */
    public function index(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $this->reviews->forProduct($product->id)->map(fn ($r) => [
                'author' => $r->author,
                'rating' => $r->rating,
                'body' => $r->body,
                'created_at' => $r->created_at?->toDateString(),
            ]),
            'meta' => $this->reviews->summaryFor($product->id),
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
