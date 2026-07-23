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
    /**
     * Per-request memo of summaries, keyed by product id.
     *
     * The service is bound `scoped`, so this lives for one request only
     * (Octane-safe) — long enough to serve a whole product grid, short enough
     * that a review added mid-request is never served stale.
     *
     * @var array<int, array{count:int, average:float}>
     */
    protected array $summaries = [];

    /** @return array{count:int, average:float} */
    public function summaryFor(int $productId): array
    {
        if (! isset($this->summaries[$productId])) {
            $this->loadSummaries([$productId]);
        }

        return $this->summaries[$productId];
    }

    /**
     * Warm the memo for many products in ONE query.
     *
     * ProductResource embeds a review summary per product, so a 24-card grid
     * used to fire 48 queries (a count + an avg each). Call this before
     * serialising a collection and the whole grid costs one aggregate query.
     *
     * @param  array<int, int>  $productIds
     */
    public function loadSummaries(array $productIds): void
    {
        $missing = array_values(array_diff(
            array_unique(array_filter($productIds)),
            array_keys($this->summaries),
        ));

        if (! $missing) {
            return;
        }

        $rows = Review::query()
            ->selectRaw('product_id, COUNT(*) as review_count, AVG(rating) as review_average')
            ->whereIn('product_id', $missing)
            ->where('approved', true)
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Products with no approved reviews get a zero row, so they are memoised
        // too and never fall back to a per-product query.
        foreach ($missing as $id) {
            $row = $rows->get($id);

            $this->summaries[$id] = [
                'count' => (int) ($row->review_count ?? 0),
                'average' => round((float) ($row->review_average ?? 0), 2),
            ];
        }
    }

    /** Drop a product's memo after its reviews change. */
    public function forgetSummary(int $productId): void
    {
        unset($this->summaries[$productId]);
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
        $review = Review::create([
            'product_id' => $productId,
            'author' => $author,
            'rating' => max(1, min(5, $rating)),
            'body' => $body,
            'approved' => (bool) app(Settings::class)->get('review.auto_approve', true),
        ]);

        // ReviewController::store adds then immediately reads the summary in the
        // same request — without this it would answer with the pre-review memo.
        $this->forgetSummary($productId);

        return $review;
    }
}
