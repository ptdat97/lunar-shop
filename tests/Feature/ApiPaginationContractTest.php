<?php

namespace Tests\Feature;

use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Modules\Core\Support\ApiPagination;
use Modules\Customer\Services\CustomerResolver;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * One pagination envelope across `/api/v1/*`: `meta { page, per_page, last_page,
 * total }`.
 *
 * Product, collection and search hand-built that shape, while `/orders` handed a
 * paginator straight to `JsonResource::collection()` and so emitted Laravel's
 * default `{ links, meta { current_page, from, to, path, … } }` instead.
 * `/customer/orders` emitted no meta at all. A mobile client could not share one
 * pagination parser across the API.
 */
class ApiPaginationContractTest extends TestCase
{
    use CreatesStorefrontData;

    /** The only shape any list endpoint may return. */
    private const KEYS = ['page', 'per_page', 'last_page', 'total'];

    private function assertPaginationMeta(array $meta): void
    {
        $this->assertSame(self::KEYS, array_keys($meta));

        foreach ($meta as $key => $value) {
            $this->assertIsInt($value, "meta.{$key} must be an integer");
        }
    }

    public function test_products_use_the_shared_envelope(): void
    {
        $this->seedBaseData();
        $this->createProduct();

        $meta = $this->getJson('/api/v1/products')->assertOk()->json('meta');

        $this->assertPaginationMeta($meta);
    }

    public function test_search_uses_the_shared_envelope(): void
    {
        $this->seedBaseData();
        $this->createProduct(['name' => 'Wool Coat']);

        $meta = $this->getJson('/api/v1/search?q=Wool')->assertOk()->json('meta');

        $this->assertPaginationMeta($meta);
    }

    public function test_orders_use_the_shared_envelope(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/orders')->assertOk();

        $this->assertPaginationMeta($response->json('meta'));

        // Laravel's paginator wrapper must be gone: `links` is not part of the
        // contract and its `meta` used different key names.
        $response->assertJsonMissingPath('links');
    }

    public function test_customer_orders_now_expose_page_counters(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/v1/customer/orders')->assertOk();

        // `data` keeps its old shape; `meta` is additive.
        $response->assertJsonStructure(['data', 'meta']);
        $this->assertPaginationMeta($response->json('meta'));
    }

    public function test_a_customer_without_orders_still_gets_the_envelope(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();

        // The guard branch must not return a differently-shaped body.
        foreach (['/api/v1/orders', '/api/v1/customer/orders'] as $uri) {
            $response = $this->actingAs($user)->getJson($uri)->assertOk();

            $this->assertSame([], $response->json('data'));
            $this->assertPaginationMeta($response->json('meta'));
        }
    }

    public function test_orders_honour_the_page_parameters(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        $customer = app(CustomerResolver::class)->forUser($user);

        foreach (range(1, 3) as $i) {
            Order::factory()->create([
                'customer_id' => $customer->id,
                'channel_id' => Channel::getDefault()->id,
                'currency_code' => Currency::getDefault()->code,
                'status' => 'payment-received',
                'sub_total' => 1000, 'discount_total' => 0, 'shipping_total' => 0,
                'tax_total' => 0, 'total' => 1000,
            ]);
        }

        // `?page=` / `?per_page=` were ignored: the response advertised
        // `last_page` but there was no way to reach the next page.
        $response = $this->actingAs($user)
            ->getJson('/api/v1/orders?per_page=2&page=2')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('meta.page'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_per_page_is_clamped_to_a_ceiling(): void
    {
        $this->seedBaseData();
        $user = $this->createUser();
        app(CustomerResolver::class)->forUser($user);

        // A client must not be able to ask for the whole table in one call.
        $this->actingAs($user)
            ->getJson('/api/v1/orders?per_page=100000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', ApiPagination::MAX_PER_PAGE);
    }

    public function test_meta_reports_the_real_page_counters(): void
    {
        $this->seedBaseData();

        foreach (range(1, 3) as $i) {
            $this->createProduct(['name' => "Tee {$i}"]);
        }

        $meta = $this->getJson('/api/v1/products?per_page=2&page=2')->assertOk()->json('meta');

        $this->assertSame(2, $meta['page']);
        $this->assertSame(2, $meta['per_page']);
        $this->assertSame(3, $meta['total']);
        $this->assertSame(2, $meta['last_page']);
    }
}
