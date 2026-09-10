<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Models\Product;
use Modules\Catalog\Contracts\SearchEngine;
use Modules\Catalog\Data\SearchQuery;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\ProductService;
use Modules\Catalog\Services\RecommendationService;
use Modules\Content\Models\PageSection;
use Modules\Content\Services\SectionRenderer;
use Modules\Customer\Services\WishlistService;
use Modules\Promotion\Services\PromotionService;
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

    /**
     * Every path that produces product cards has to use the same relation set.
     *
     * Four services were each carrying their own copy of that list and every
     * copy had drifted differently — recommendations cost 112 queries for eight
     * products, promotions 87. The lists are now one static method, and this is
     * what stops a fifth copy appearing.
     */
    public function test_no_card_path_loads_options_per_product(): void
    {
        $this->seedBaseData();

        foreach (range(1, 6) as $i) {
            $this->createProduct(['slug' => "card-{$i}", 'options' => ['Color' => 'Black']]);
        }

        $product = Product::query()->first();

        $paths = [
            'gợi ý' => fn () => app(RecommendationService::class)
                ->forProduct($product, 6),
            'khuyến mãi' => fn () => app(PromotionService::class)
                ->productsOnSale(6),
        ];

        foreach ($paths as $name => $fetch) {
            $items = $fetch();

            DB::flushQueryLog();
            DB::enableQueryLog();

            ProductResource::collection($items)->resolve();

            $optionQueries = collect(DB::getQueryLog())
                ->filter(fn ($q) => str_contains($q['query'], 'lunar_product_option'))
                ->count();

            DB::disableQueryLog();

            $this->assertSame(0, $optionQueries, "Đường [{$name}] vẫn nạp option lẻ theo từng sản phẩm.");
        }
    }

    /** The shared set must actually carry both sides of the option join. */
    public function test_the_shared_card_relations_cover_the_option_groups(): void
    {
        $relations = ProductService::cardRelations();

        $this->assertArrayHasKey('variants', $relations);
        $this->assertContains('productOptions.values', $relations);

        // The card blade reads `$product->defaultUrl?->slug` for its href. Left
        // out, that is one query per card on every server-rendered grid, and no
        // option-group assertion above would notice.
        $this->assertContains('defaultUrl', $relations);
    }

    /**
     * The home page's product-tabs section, when an admin picks products by
     * hand rather than falling back to "newest".
     *
     * That branch had its own relation list, and the list was missing
     * `defaultUrl` — eight of the thirteen queries it ran for eight cards were
     * one per card, resolving the link the card links to. Unlike the API paths
     * above, this one renders Blade and never serialises option groups, so the
     * option assertions would have let it through.
     */
    public function test_a_hand_picked_product_tabs_section_does_not_query_per_card(): void
    {
        $this->seedBaseData();

        foreach (range(1, 8) as $i) {
            $this->createProduct(['slug' => "tab-{$i}", 'options' => ['Color' => 'Black']]);
        }

        $ids = Product::query()->pluck('id')->all();

        // Two cards against eight, on a page handle of this test's own so no
        // other seeded section joins the count.
        $small = $this->costOfTabsSection(array_slice($ids, 0, 2));
        $large = $this->costOfTabsSection($ids);

        $this->assertLessThan(
            $small + 4,
            $large,
            "Section product-tabs tốn {$large} truy vấn cho 8 thẻ nhưng chỉ {$small} cho 2 — vẫn còn truy vấn lẻ theo từng thẻ.",
        );
    }

    /**
     * Every service that hands product models to a card must hand them over
     * with the card's relations already loaded.
     *
     * Counting queries catches an N+1 on the paths a test can drive. This
     * catches the ones it cannot: it asks each service directly whether the
     * models it returns are ready to render. Eight services had their own copy
     * of the relation list; the wishlist's was missing `defaultUrl` and
     * `prices`, which cost 108 queries for 8 cards.
     */
    public function test_every_card_service_returns_products_ready_to_render(): void
    {
        $this->seedBaseData();

        foreach (range(1, 3) as $i) {
            $this->createProduct(['slug' => "ready-{$i}", 'options' => ['Color' => 'Black']]);
        }

        $user = User::factory()->create();
        $first = Product::query()->first();

        app(WishlistService::class)->toggle($user, $first->id);

        $sources = [
            'wishlist' => fn () => app(WishlistService::class)
                ->productsFor($user),
            'bySlugs' => fn () => app(ProductService::class)
                ->bySlugs(['ready-1', 'ready-2']),
            'byIds' => fn () => app(ProductService::class)
                ->byIds([$first->id]),
            'related' => fn () => app(ProductService::class)
                ->related($first, 3),
        ];

        // What the card blade and its composer read off a product.
        $needed = ['defaultUrl', 'thumbnail', 'brand', 'media', 'variants'];

        foreach ($sources as $name => $fetch) {
            $products = $fetch();

            $this->assertNotEmpty($products, "Nguồn [{$name}] không trả sản phẩm nào để kiểm.");

            foreach ($products as $product) {
                foreach ($needed as $relation) {
                    $this->assertTrue(
                        $product->relationLoaded($relation),
                        "Nguồn [{$name}] trả sản phẩm chưa nạp [{$relation}] — thẻ sẽ tự truy vấn.",
                    );
                }

                foreach ($product->variants as $variant) {
                    $this->assertTrue(
                        $variant->relationLoaded('prices'),
                        "Nguồn [{$name}] trả variant chưa nạp giá.",
                    );
                }
            }
        }
    }

    /** Render a product-tabs section for these ids and count the queries. */
    private function costOfTabsSection(array $productIds): int
    {
        $handle = 'cost-'.count($productIds);

        PageSection::create([
            'page_handle' => $handle,
            'type' => 'product-tabs',
            'sort' => 1,
            'enabled' => true,
            'settings' => ['tabs' => [['label' => 'Tab', 'product_ids' => $productIds]]],
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        app(SectionRenderer::class)->render($handle);

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }
}
