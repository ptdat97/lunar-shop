<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Http\Resources\ProductResource;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * What a product listing costs in queries, measured the only way that catches
 * an N+1: by asking whether the cost grows with the number of products.
 *
 * The suite already had an N+1 test for this endpoint, but it looked for one
 * specific pattern — the parent product being re-fetched per variant. A
 * different N+1 walked straight past it: every card serialises its option
 * groups, and both the product's options and each variant's values were being
 * loaded per product. `/search` was running 1298 queries for 24 cards.
 *
 * A fixed ceiling would have to be loosened every time a feature adds a join.
 * The slope does not: eager-loaded or not, ten products must cost about what
 * two do.
 */
class ListingQueryCostTest extends TestCase
{
    use CreatesStorefrontData;

    /** Serialise a listing exactly as the API does, and count the queries. */
    private function costOfListing(int $products): int
    {
        foreach (range(1, $products) as $i) {
            $this->createProduct([
                'slug' => "cost-{$i}",
                'options' => ['Color' => 'Black', 'Size' => 'M'],
            ]);
        }

        $result = app(SearchEngine::class)->search(new SearchQuery(perPage: 50, withFacets: false));

        DB::flushQueryLog();
        DB::enableQueryLog();

        // Resource serialisation is where the per-card work happens, so it has
        // to be inside the measurement — counting only the search query would
        // report a listing as cheap while every card queried on render.
        ProductResource::collection($result->items)->resolve();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    public function test_serialising_a_listing_does_not_cost_more_per_product(): void
    {
        $this->seedBaseData();

        $small = $this->costOfListing(2);
        $large = $this->costOfListing(8);

        // Six more products may add a bounded amount of work (a chunked
        // whereIn, say) but must not add a query per product.
        $this->assertLessThan(
            $small + 6,
            $large,
            "Truy vấn tăng theo số sản phẩm: {$small} cho 2 sản phẩm, {$large} cho 8 — có N+1 trong lúc serialise."
        );
    }

    /**
     * The option groups are what regressed, so pin that they are actually
     * served from memory rather than fetched per card.
     */
    public function test_option_groups_are_read_from_the_eager_loaded_relations(): void
    {
        $this->seedBaseData();

        foreach (range(1, 5) as $i) {
            $this->createProduct(['slug' => "opt-{$i}", 'options' => ['Color' => 'Black']]);
        }

        $result = app(SearchEngine::class)->search(new SearchQuery(perPage: 50, withFacets: false));

        DB::flushQueryLog();
        DB::enableQueryLog();

        $payload = ProductResource::collection($result->items)->resolve();

        $optionQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'lunar_product_options')
                || str_contains($q['query'], 'lunar_product_option_values'))
            ->count();

        DB::disableQueryLog();

        $this->assertSame(0, $optionQueries, 'Option vẫn đang được nạp lẻ cho từng sản phẩm.');

        // And the data still arrives — a listing that queries nothing because
        // it serialises nothing would pass the count and fail the shopper.
        $withOptions = collect($payload)->filter(fn ($row) => ! empty($row['options']));

        $this->assertGreaterThan(0, $withOptions->count(), 'Không sản phẩm nào còn nhóm option.');
    }
}
