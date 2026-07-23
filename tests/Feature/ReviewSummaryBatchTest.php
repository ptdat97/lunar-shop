<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Product;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Models\Review;
use Modules\Catalog\Services\ReviewService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Review summaries are embedded in every product payload, so they are read once
 * per card in a grid. They must be batched into a single aggregate query.
 *
 * Measured before batching: a 24-product search response fired 48 review
 * queries (a COUNT + an AVG each) out of 61 total.
 */
class ReviewSummaryBatchTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array<int, int> product ids */
    private function productsWithReviews(int $count): array
    {
        $ids = [];

        foreach (range(1, $count) as $i) {
            $product = $this->createProduct(['slug' => "reviewed-tee-{$i}"]);
            $ids[] = $product->id;

            Review::create([
                'product_id' => $product->id,
                'author' => "Reviewer {$i}",
                'rating' => 4,
                'body' => 'Good',
                'approved' => true,
            ]);
        }

        return $ids;
    }

    private function reviewQueryCount(callable $fn): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $fn();

        $count = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'product_reviews'))
            ->count();

        DB::disableQueryLog();

        return $count;
    }

    public function test_serialising_a_collection_costs_one_review_query(): void
    {
        $this->seedBaseData();
        $ids = $this->productsWithReviews(5);

        $products = Product::whereIn('id', $ids)->get();

        $queries = $this->reviewQueryCount(
            fn () => ProductResource::collection($products)->resolve(request()),
        );

        $this->assertSame(
            1,
            $queries,
            'the whole grid must resolve review summaries in one aggregate query',
        );
    }

    public function test_summaries_are_correct_per_product_not_shared(): void
    {
        $this->seedBaseData();

        $quiet = $this->createProduct(['slug' => 'quiet-tee']);
        $busy = $this->createProduct(['slug' => 'busy-tee']);

        Review::create(['product_id' => $busy->id, 'author' => 'A', 'rating' => 5, 'body' => 'x', 'approved' => true]);
        Review::create(['product_id' => $busy->id, 'author' => 'B', 'rating' => 3, 'body' => 'y', 'approved' => true]);
        // Unapproved must not count towards either figure.
        Review::create(['product_id' => $busy->id, 'author' => 'C', 'rating' => 1, 'body' => 'z', 'approved' => false]);

        $service = app(ReviewService::class);
        $service->loadSummaries([$quiet->id, $busy->id]);

        $this->assertSame(['count' => 2, 'average' => 4.0], $service->summaryFor($busy->id));
        // A product with no approved reviews still gets a memoised zero row.
        $this->assertSame(['count' => 0, 'average' => 0.0], $service->summaryFor($quiet->id));
    }

    public function test_a_product_with_no_reviews_does_not_requery(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'unreviewed-tee']);

        $service = app(ReviewService::class);
        $service->loadSummaries([$product->id]);

        // Already memoised as zero — reading it again must hit no database.
        $queries = $this->reviewQueryCount(fn () => $service->summaryFor($product->id));

        $this->assertSame(0, $queries, 'a zero summary must be memoised, not re-queried');
    }

    public function test_adding_a_review_invalidates_the_memo(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'fresh-tee']);

        $service = app(ReviewService::class);
        $this->assertSame(0, $service->summaryFor($product->id)['count']);

        $service->add($product->id, 'Alice', 5, 'Great');

        // ReviewController::store adds then reads the summary in the same
        // request; a stale memo would answer with the pre-review count.
        $this->assertSame(1, $service->summaryFor($product->id)['count']);
    }

    public function test_the_search_endpoint_does_not_n_plus_one_over_reviews(): void
    {
        $this->seedBaseData();
        $this->productsWithReviews(6);

        $queries = $this->reviewQueryCount(
            fn () => $this->getJson('/api/v1/search')->assertSuccessful(),
        );

        $this->assertLessThanOrEqual(
            1,
            $queries,
            'the search grid must not read review summaries per product',
        );
    }
}
