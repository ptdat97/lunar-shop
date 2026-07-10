<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Lunar\Facades\CartSession;
use Lunar\Facades\ShippingManifest;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Modules\Checkout\Services\CheckoutService;
use Modules\Core\Support\Settings;
use Modules\Inventory\Filament\Pages\InventorySettingsPage;
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
        $product->variants->first()->update(['stock' => 5, 'purchasable' => 'in_stock']);

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
        $this->assertSame(3, $variant->fresh()->stock, 'reserved');

        // Default is 60 minutes: a 30-minute-old order is still the shopper's.
        Artisan::call('orders:expire-abandoned');
        $this->assertSame(OrderStatus::AWAITING_PAYMENT, $order->fresh()->status);

        // The shop starts a flash sale and wants the units back sooner.
        app(Settings::class)->put('inventory', ['hold_minutes' => 20]);

        Artisan::call('orders:expire-abandoned');
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock, 'units back on sale');
    }

    public function test_an_explicit_flag_still_outranks_the_setting(): void
    {
        $this->seedBaseData();
        app(Settings::class)->put('inventory', ['hold_minutes' => 10080]);
        [$order] = $this->unpaidGatewayOrder(ageMinutes: 30);

        // A one-off sweep after fixing the gateway, without touching the setting.
        Artisan::call('orders:expire-abandoned', ['--minutes' => 5]);

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
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
     * `Settings::put()` replaces the whole group, so a page that saves only the
     * field it changed silently nulls its siblings. Measured: saving just
     * `hold_minutes` left `low_stock_threshold` as NULL.
     */
    public function test_the_admin_page_saves_every_field_it_owns(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $this->actingAs($staff, 'staff');

        Livewire::test(InventorySettingsPage::class)
            ->fillForm(['low_stock_threshold' => 9, 'hold_minutes' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(Settings::class);
        $this->assertSame(9, (int) $settings->get('inventory.low_stock_threshold'));
        $this->assertSame(25, (int) $settings->get('inventory.hold_minutes'));
    }

    public function test_the_admin_page_rejects_a_hold_window_below_the_floor(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $this->actingAs($staff, 'staff');

        Livewire::test(InventorySettingsPage::class)
            ->fillForm(['low_stock_threshold' => 5, 'hold_minutes' => 1])
            ->call('save')
            ->assertHasFormErrors(['hold_minutes']);
    }
}
