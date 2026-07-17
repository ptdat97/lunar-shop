<?php

namespace Tests\Feature;

use Illuminate\Testing\TestResponse;
use Modules\Order\Http\Resources\OrderResource;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * One order contract across the API.
 *
 * `POST /api/v1/checkout` and `GET /api/v1/orders/{id}` both describe an Order,
 * but used to return different shapes from two separate OrderResource classes
 * (9 keys vs 23), so a client could not share one parser. The Order module now
 * owns the single resource.
 */
class OrderContractTest extends TestCase
{
    use CreatesStorefrontData;

    /** Place a COD order through the real endpoints and return the response. */
    private function placeOrder(): TestResponse
    {
        $product = $this->createProduct();

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->skus->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])
            ->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])
            ->assertSuccessful();

        return $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();
    }

    public function test_only_one_order_resource_class_exists(): void
    {
        $this->assertTrue(class_exists(OrderResource::class));

        $this->assertFileDoesNotExist(
            base_path('modules/Checkout/Http/Resources/OrderResource.php'),
            'the duplicate Checkout OrderResource should be gone',
        );
    }

    public function test_placed_order_returns_the_full_order_contract(): void
    {
        $this->seedBaseData();

        $this->placeOrder()->assertJsonStructure([
            'data' => [
                'id', 'reference', 'status', 'placed_at',
                'total', 'sub_total', 'shipping_total', 'tax_total',
                'can_return',
                'lines' => [['id', 'description', 'identifier', 'quantity', 'unit_price', 'sub_total']],
                'shipping_address' => ['name', 'line_one', 'city'],
            ],
        ]);
    }

    public function test_placed_order_keeps_the_old_keys(): void
    {
        $this->seedBaseData();

        // The previous Checkout resource exposed exactly these; the superset
        // must stay backwards compatible for existing clients.
        $data = $this->placeOrder()->json('data');

        foreach (['id', 'reference', 'status', 'total', 'placed_at', 'lines'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertArrayHasKey('description', $data['lines'][0]);
        $this->assertArrayHasKey('quantity', $data['lines'][0]);
        $this->assertArrayHasKey('sub_total', $data['lines'][0]);
    }

    public function test_checkout_and_order_detail_agree_on_the_shape(): void
    {
        $this->seedBaseData();

        $user = $this->createUser();
        $this->actingAs($user);

        $placed = $this->placeOrder()->json('data');

        $fetched = $this->getJson('/api/v1/orders/'.$placed['id'])
            ->assertOk()
            ->json('data');

        // Same entity, same contract — the keys must match exactly.
        $this->assertSame(array_keys($placed), array_keys($fetched));
        $this->assertSame($placed['reference'], $fetched['reference']);
    }
}
