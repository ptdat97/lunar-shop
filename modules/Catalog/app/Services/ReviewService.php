<?php

namespace Modules\Catalog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Catalog\Models\Review;
use Modules\Core\Support\Settings;

/**
 * Single source of review logic — used by the API controller AND the
 * product.resource enrichment, so the payload and the endpoint never drift.
 */
class ReviewService
{
    /** @return array{count:int, average:float} */
    public function summaryFor(int $productId): array
    {
        $reviews = Review::where('product_id', $productId)->where('approved', true);

        return [
            'count' => (clone $reviews)->count(),
            'average' => round((float) (clone $reviews)->avg('rating'), 2),
        ];
    }

    /**
     * Approved reviews, newest first. Paginated: a popular product accumulates
     * reviews without bound, and the endpoint used to return every one of them.
     *
     * @return LengthAwarePaginator<int, Review>
     */
    public function forProduct(int $productId, int $perPage = 20, int $page = 1)
    {
        return Review::where('product_id', $productId)
            ->where('approved', true)
            ->latest()
            ->paginate(perPage: $perPage, page: $page);
    }

    public function add(int $productId, string $author, int $rating, ?string $body): Review
    {
        return Review::create([
            'product_id' => $productId,
            'author' => $author,
            'rating' => max(1, min(5, $rating)),
            'body' => $body,
            'approved' => (bool) app(Settings::class)->get('review.auto_approve', true),
        ]);
    }
}
