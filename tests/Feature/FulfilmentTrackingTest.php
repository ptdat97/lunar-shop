<?php

namespace Tests\Feature;

use Lunar\Core\Contracts\Actions\Fulfilment\AddsFulfilmentTracking;
use Lunar\Core\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Tracking codes have somewhere to live now, and it costs no carrier contract.
 *
 * The shipping roadmap (docs/roadmap.md P0.5) parked carrier integration until
 * a GHN/GHTK contract exists, and listed "nowhere to store a tracking number"
 * among the things that would have to be built first — `lunar_orders` had no
 * such column. Lunar 2.0 closed that on its own: `lunar_fulfilment_trackings`,
 * with the panel's AddTrackingDialog writing to it.
 *
 * The part that matters for a shop with no contract is what this pins down:
 * `AddFulfilmentTracking` looks a carrier up in the manifest and SKIPS format
 * validation when it finds none. So a free-text Vietnamese carrier records
 * fine, no registration needed — which is the whole manual workflow the
 * roadmap describes, minus the sticky note.
 */
class FulfilmentTrackingTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    public function test_tracking_records_against_an_unregistered_carrier(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['stock' => 5]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $fulfilment = Order::latest('id')->firstOrFail()->fulfilments()->firstOrFail();

        // Not one of Lunar's four defaults (royal-mail, dpd, ups, fedex), which
        // are the UK/US carriers a Vietnamese shop will never use.
        $tracking = app(AddsFulfilmentTracking::class)->execute($fulfilment, [
            'carrier' => 'ghtk',
            'tracking_number' => 'S12345678901',
            'shipping_method' => 'COD nội thành',
        ]);

        $this->assertSame('ghtk', $tracking->carrier);
        $this->assertSame('S12345678901', $tracking->tracking_number);
        $this->assertTrue($fulfilment->trackings()->whereKey($tracking->id)->exists());
    }
}
