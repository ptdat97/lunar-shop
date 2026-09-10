<?php

namespace Tests\Feature;

use Lunar\Core\Facades\CartSession;
use Lunar\Core\Models\Order;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * The shopper must pay for the cart they were shown.
 *
 * Lunar's checkout guide recommends fingerprinting between review and payment,
 * and this shop had no equivalent at all. The fingerprint covers the lines,
 * their quantities and subtotals, the coupon and the currency — exactly the
 * things that decide the number the shopper thought they were agreeing to.
 *
 * What it protects against is mundane and common: a second tab changing the
 * quantity, a promotion expiring between page load and submit, a line dropped
 * because the last one sold. Without it the order is placed at the recalculated
 * total, and the first the shopper knows is the charge.
 */
class CheckoutFingerprintTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private function readyCart(): void
    {
        $product = $this->createProduct(['stock' => 10]);

        $this->postJson('/api/v1/cart', [
            'sku_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
    }

    public function test_the_checkout_page_emits_a_fingerprint(): void
    {
        $this->seedBaseData();
        $this->readyCart();

        $html = $this->get('/checkout')->assertOk()->getContent();

        preg_match('/name="fingerprint" value="([^"]*)"/', $html, $m);

        $this->assertNotEmpty($m[1] ?? '', 'Trang checkout không phát dấu vân tay giỏ.');
        $this->assertSame(CartSession::current()->fingerprint(), $m[1]);
    }

    public function test_an_unchanged_cart_places_the_order(): void
    {
        $this->seedBaseData();
        $this->readyCart();

        $fingerprint = CartSession::current()->fingerprint();

        $this->postJson('/api/v1/checkout', [
            'payment_type' => 'cod',
            'fingerprint' => $fingerprint,
        ])->assertSuccessful();

        $this->assertNotNull(Order::latest('id')->first());
    }

    /** The case the whole thing exists for. */
    public function test_a_changed_cart_is_refused(): void
    {
        $this->seedBaseData();
        $this->readyCart();

        $stale = CartSession::current()->fingerprint();

        // Khách mở tab khác và đổi số lượng — tổng tiền giờ khác con số họ đang
        // nhìn ở tab checkout.
        $line = CartSession::current()->lines->first();
        $this->patchJson('/api/v1/cart/lines/'.$line->id, ['quantity' => 3])->assertSuccessful();

        $this->postJson('/api/v1/checkout', [
            'payment_type' => 'cod',
            'fingerprint' => $stale,
        ])->assertStatus(422)->assertJsonValidationErrors('fingerprint');

        $this->assertNull(Order::latest('id')->first(), 'Đơn vẫn được đặt dù giỏ đã đổi.');
    }

    /**
     * Existing API clients do not send one yet. Requiring it would break them;
     * they simply go without this protection until they send it.
     */
    public function test_a_client_that_sends_nothing_still_checks_out(): void
    {
        $this->seedBaseData();
        $this->readyCart();

        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $this->assertNotNull(Order::latest('id')->first());
    }
}
