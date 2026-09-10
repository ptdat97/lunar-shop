<?php

namespace Tests\Feature;

use Lunar\Core\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * A placed order must arrive with a fulfilment record.
 *
 * The panel ships the whole fulfilment UI (FulfilmentCard, ship / add-tracking /
 * split / merge / hold dialogs) and it all acts on fulfilment records. Our own
 * code creates none — Lunar's OrderObserver raises `OrderPlaced`, and Lunar's
 * `EnsureInitialFulfilmentForOrder` listener creates the record from that.
 *
 * That chain runs entirely inside the package, which is exactly why it deserves
 * a test here: nothing in this repo would break if it stopped firing, and the
 * first symptom would be staff opening an order and finding nothing to ship —
 * with the committed stock then held forever, since units only return to the
 * sellable pool on dispatch or cancellation.
 */
class OrderFulfilmentWiringTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    public function test_a_placed_order_gets_an_initial_fulfilment(): void
    {
        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 2,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $order = Order::latest('id')->with('fulfilments.lines')->first();

        $this->assertNotNull($order, 'Không đặt được đơn nào.');

        $this->assertGreaterThan(
            0,
            $order->fulfilments->count(),
            'Đơn đã đặt không có fulfilment nào — UI giao hàng của panel sẽ không có gì để thao tác.',
        );

        // The fulfilment must actually cover the goods, not just exist.
        $fulfilledQuantity = $order->fulfilments
            ->flatMap->lines
            ->sum('quantity');

        $this->assertSame(
            2,
            (int) $fulfilledQuantity,
            'Fulfilment không phủ đủ số lượng đã đặt.',
        );
    }
}
