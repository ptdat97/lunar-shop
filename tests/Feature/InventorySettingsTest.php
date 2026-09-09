<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Modules\Checkout\Services\CheckoutService;
use Modules\Core\Support\Settings;
use Modules\Inventory\Services\InventoryService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * "How long may an unpaid order hold stock" is a shop decision, edited in
 * Admin → Settings → Inventory. It used to be a `--minutes=60` literal in
 * `routes/console.php`, changeable only by a deploy.
 */
class InventorySettingsTest extends TestCase
{
    use CreatesStorefrontData;

    /** @return array{0: Order, 1: ProductVariant} */
    private function unpaidGatewayOrder(int $ageMinutes): array
    {
        $product = $this->createProduct(['price' => 5000]);
        $this->setStock($product->variants->first(), 5);

        CartSession::add($product->variants->first(), 2);
        $cart = CartSession::current();
        $address = $this->shippingPayload(['postcode' => '00000']);
        $cart->setShippingAddress($address);
        $cart->setBillingAddress($address);
        $cart->calculate();
        $cart->setShippingOption(ShippingManifest::getOptions($cart)->first())->calculate();

        $order = app(CheckoutService::class)->placeOrder('vnpay');
        Order::whereKey($order->id)->update(['created_at' => now()->subMinutes($ageMinutes)]);

        return [$order, $product->variants->first()];
    }

    public function test_the_saved_setting_decides_when_stock_comes_back(): void
    {
        $this->seedBaseData();
        [$order, $variant] = $this->unpaidGatewayOrder(ageMinutes: 30);
        $this->assertSame(3, $variant->fresh()->getTotalInventory(), 'reserved');

        // Default is 60 minutes: a 30-minute-old order is still the shopper's.
        Artisan::call('orders:expire-abandoned');
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, OrderStatus::of($order->fresh()));

        // The shop starts a flash sale and wants the units back sooner.
        app(Settings::class)->put('inventory', ['hold_minutes' => 20]);

        Artisan::call('orders:expire-abandoned');
        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
        $this->assertSame(5, $variant->fresh()->getTotalInventory(), 'units back on sale');
    }

    public function test_an_explicit_flag_still_outranks_the_setting(): void
    {
        $this->seedBaseData();
        app(Settings::class)->put('inventory', ['hold_minutes' => 10080]);
        [$order] = $this->unpaidGatewayOrder(ageMinutes: 30);

        // A one-off sweep after fixing the gateway, without touching the setting.
        Artisan::call('orders:expire-abandoned', ['--minutes' => 5]);

        $this->assertSame(OrderStatus::CANCELLED, OrderStatus::of($order->fresh()));
    }

    /**
     * A 0 here would cancel orders while the shopper is still on the bank's page,
     * and nobody would notice until the stock was already gone.
     */
    public function test_the_hold_window_is_clamped(): void
    {
        $settings = app(Settings::class);
        $inventory = app(InventoryService::class);

        $settings->put('inventory', ['hold_minutes' => 0]);
        $this->assertSame(InventoryService::MIN_HOLD_MINUTES, $inventory->holdMinutes());

        $settings->put('inventory', ['hold_minutes' => 999999]);
        $this->assertSame(InventoryService::MAX_HOLD_MINUTES, $inventory->holdMinutes());
    }

    public function test_it_falls_back_to_the_default_when_unset(): void
    {
        $this->assertSame(
            InventoryService::DEFAULT_HOLD_MINUTES,
            app(InventoryService::class)->holdMinutes()
        );
    }
}
