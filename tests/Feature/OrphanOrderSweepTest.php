<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Modules\Checkout\Services\CheckoutService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * An order row is created inside `CreateOrder`'s transaction, which commits.
 * Only *then* does the payment driver stamp `placed_at` + `meta.payment_type` in
 * a second statement. A process that dies between the two leaves an order nobody
 * ever completed.
 *
 * `placed_at IS NULL` is the reliable signal: every payment driver sets it the
 * moment authorize() succeeds, so an order without it never finished checkout.
 *
 * **The stock half of this is no longer a risk.** The shop used to reserve stock
 * inside that same transaction, so an orphan held units forever and the sweep
 * existed to get them back. Lunar 2.0 derives the commitment from orders that
 * have actually been PLACED, so an orphan holds nothing — the failure mode is
 * gone at the source rather than swept up afterwards. The sweep still runs, to
 * stop abandoned rows accumulating and to close gateway orders nobody paid.
 */
class OrphanOrderSweepTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array{0: Cart, 1: ProductVariant} */
    private function readyCart(int $stock = 5, int $qty = 2): array
    {
        $product = $this->createProduct(['price' => 5000]);
        $this->setStock($product->variants->first(), $stock);

        CartSession::add($product->variants->first(), $qty);
        $cart = CartSession::current();
        $address = $this->shippingPayload(['postcode' => '00000']);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
        $cart->calculate();
        $cart->setShippingOption(ShippingManifest::getOptions($cart)->first())->calculate();

        return [$cart->refresh(), $product->variants->first()];
    }

    /** Simulates a crash after CreateOrder commits, before the driver's update runs. */
    private function orphanOrder(): array
    {
        [$cart, $variant] = $this->readyCart();
        $order = $cart->createOrder();

        return [$order, $variant];
    }

    public function test_an_orphaned_draft_holds_no_stock_and_is_still_swept(): void
    {
        $this->seedBaseData();
        [$order, $variant] = $this->orphanOrder();

        // The order exists but was never placed, so it holds no stock at all.
        $this->assertSame(5, $variant->fresh()->getTotalInventory(), 'an unplaced order holds nothing');
        $this->assertNull($order->placed_at, 'checkout never completed');

        Order::whereKey($order->id)->update(['created_at' => now()->subDay()]);
        Artisan::call('orders:expire-abandoned', ['--minutes' => 60]);

        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $variant->fresh()->getTotalInventory());
        $this->assertNotNull($order->fresh()->stock_released_at);
    }

    public function test_a_fresh_orphan_is_left_alone(): void
    {
        $this->seedBaseData();
        [$order, $variant] = $this->orphanOrder();

        // Created just now — a checkout still in flight must not be cancelled.
        Artisan::call('orders:expire-abandoned', ['--minutes' => 60]);

        $this->assertNotSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $variant->fresh()->getTotalInventory());
    }

    /** Bank transfer sits in awaiting-payment for days and is settled by hand. */
    public function test_bank_transfer_order_is_never_swept(): void
    {
        $this->seedBaseData();
        [, $variant] = $this->readyCart();

        $order = app(CheckoutService::class)->placeOrder('bank-transfer');
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order));
        $this->assertNotNull($order->placed_at, 'offline driver stamps placed_at');

        Order::whereKey($order->id)->update(['created_at' => now()->subDay()]);
        Artisan::call('orders:expire-abandoned', ['--minutes' => 60]);

        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));
        $this->assertSame(3, $variant->fresh()->getTotalInventory(), 'still reserved for the buyer');
    }

    /** The pre-existing behaviour: an abandoned gateway order still expires. */
    public function test_abandoned_gateway_order_is_still_swept(): void
    {
        $this->seedBaseData();
        [, $variant] = $this->readyCart();

        $order = app(CheckoutService::class)->placeOrder('vnpay');
        Order::whereKey($order->id)->update(['created_at' => now()->subDay()]);

        Artisan::call('orders:expire-abandoned', ['--minutes' => 60]);

        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $variant->fresh()->getTotalInventory());
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->seedBaseData();
        [$order, $variant] = $this->orphanOrder();
        Order::whereKey($order->id)->update(['created_at' => now()->subDay()]);

        Artisan::call('orders:expire-abandoned', ['--minutes' => 60, '--dry-run' => true]);

        $this->assertNotSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $variant->fresh()->getTotalInventory());
    }
}
