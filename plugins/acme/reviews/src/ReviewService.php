<?php

namespace Acme\Reviews;

use Acme\Reviews\Models\Review;
use Modules\Platform\Plugin\PluginSettings;

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

    /** @return \Illuminate\Support\Collection<int, Review> */
    public function forProduct(int $productId)
    {
        return Review::where('product_id', $productId)
            ->where('approved', true)
            ->latest()
            ->get();
    }

    public function add(int $productId, string $author, int $rating, ?string $body): Review
    {
        return Review::create([
            'product_id' => $productId,
            'author' => $author,
            'rating' => max(1, min(5, $rating)),
            'body' => $body,
            'approved' => (bool) PluginSettings::for('acme/reviews')->get('auto_approve', true),
        ]);
    }
}
