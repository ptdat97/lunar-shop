<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Lunar\Core\Facades\CartSession;
use Lunar\Core\Facades\ShippingManifest;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\ProductVariant;
use Lunar\Core\Models\Staff;
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

    /**
     * The settings screen writes what this service reads.
     *
     * Carried over from the Filament era, rewritten against the panel: the
     * screen changed, the contract between it and InventoryService did not.
     */
    public function test_the_settings_screen_saves_every_field_it_owns(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        $this->put(route('panel.shop.settings.inventory.update'), [
            'low_stock_threshold' => 9,
            'hold_minutes' => 25,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = app(Settings::class);

        $this->assertSame(9, (int) $settings->get('inventory.low_stock_threshold'));
        $this->assertSame(25, (int) $settings->get('inventory.hold_minutes'));
    }

    /**
     * holdMinutes() clamps what it reads to [MIN, MAX]. A form that accepted a
     * value below the floor would report "saved" while the system quietly used
     * a different number — so the form has to refuse it.
     */
    public function test_the_settings_screen_rejects_a_hold_window_outside_the_service_bounds(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        $this->put(route('panel.shop.settings.inventory.update'), [
            'low_stock_threshold' => 5,
            'hold_minutes' => InventoryService::MIN_HOLD_MINUTES - 1,
        ])->assertSessionHasErrors('hold_minutes');

        $this->put(route('panel.shop.settings.inventory.update'), [
            'low_stock_threshold' => 5,
            'hold_minutes' => InventoryService::MAX_HOLD_MINUTES + 1,
        ])->assertSessionHasErrors('hold_minutes');

        // The bound itself is accepted — an off-by-one here would lock the
        // admin out of a value the service honours.
        $this->put(route('panel.shop.settings.inventory.update'), [
            'low_stock_threshold' => 5,
            'hold_minutes' => InventoryService::MIN_HOLD_MINUTES,
        ])->assertSessionHasNoErrors();

        $this->assertSame(InventoryService::MIN_HOLD_MINUTES, app(InventoryService::class)->holdMinutes());
    }

    /**
     * The low-stock threshold has to reach the card that actually uses it.
     *
     * Lunar's own LowStockWidget is what shows low stock on the dashboard, and
     * it reads `lunar.panel.dashboard.low_stock_threshold` — a different key
     * from where this shop stores the setting. Until the two were wired
     * together the settings field was decoration: an admin set 5 and the
     * dashboard went on using Lunar's default of 10.
     *
     * The widget itself is left alone on purpose. It already knows to skip
     * variants sold regardless of stock and to ignore invisible products;
     * rebuilding that would be duplicating upstream work for nothing.
     */
    public function test_the_configured_threshold_feeds_lunars_low_stock_widget(): void
    {
        app(Settings::class)->put('inventory', [
            'low_stock_threshold' => 4,
            'hold_minutes' => InventoryService::DEFAULT_HOLD_MINUTES,
        ]);

        // Put Lunar's own default back so a pass cannot come from a value the
        // provider set before this test saved anything.
        config(['lunar.panel.dashboard.low_stock_threshold' => 10]);

        // Re-run the provider. Its hook is registered on `booted()`, and the
        // app is already booted here, so it fires straight away — the same code
        // path a real request takes.
        $this->app->register(\Modules\Inventory\Providers\InventoryServiceProvider::class, force: true);

        $this->assertSame(4, (int) config('lunar.panel.dashboard.low_stock_threshold'));
        $this->assertSame(4, app(InventoryService::class)->lowStockThreshold());
    }
}
