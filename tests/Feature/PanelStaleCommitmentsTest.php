<?php

namespace Tests\Feature;

use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\Staff;
use Lunar\Panel\Dashboard\DashboardRange;
use Lunar\Panel\PanelManager;
use Modules\Inventory\Panel\StaleCommitmentsWidget;
use Modules\Inventory\Services\InventoryService;
use Modules\Order\Support\OrderStatus;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * The dashboard card for orders that hold stock and never ship.
 *
 * Committed units only return to the sellable pool when an order is dispatched
 * or cancelled, so a paid-and-forgotten order holds its units forever and the
 * shelf quietly stops selling with nothing to show why. The old Stock Overview
 * page carried this as a banner; it went with Filament, and
 * `staleCommitments()` has been answering to nobody since.
 */
class PanelStaleCommitmentsTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBaseData();
    }

    private function paidOrder(int $daysAgo): Order
    {
        return Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            ...$this->orderAttributesFor(OrderStatus::PAYMENT_RECEIVED),
            'reference' => 'ORD-'.uniqid(),
            'placed_at' => now()->subDays($daysAgo),
        ]);
    }

    private function payload(): array
    {
        return app(StaleCommitmentsWidget::class)->data(DashboardRange::ThirtyDays);
    }

    public function test_it_reports_orders_past_the_stale_threshold(): void
    {
        $stale = $this->paidOrder(InventoryService::STALE_COMMITMENT_DAYS + 2);
        $this->paidOrder(0);

        $data = $this->payload();

        $this->assertSame(1, $data['count']);
        $this->assertSame($stale->reference, $data['orders'][0]['reference']);
        $this->assertStringContainsString("/panel/orders/{$stale->id}", $data['orders'][0]['url']);
    }

    /** A fresh order is not a problem, and must not be reported as one. */
    public function test_a_recent_order_is_not_stale(): void
    {
        $this->paidOrder(0);

        $this->assertSame(0, $this->payload()['count']);
    }

    /**
     * A warning, not a report: the count is honest but the list is capped, so a
     * shop with hundreds of stuck orders gets the number and the oldest few
     * rather than hundreds of rows on its dashboard.
     */
    public function test_the_list_is_capped_while_the_count_is_not(): void
    {
        foreach (range(1, 11) as $i) {
            $this->paidOrder(InventoryService::STALE_COMMITMENT_DAYS + $i);
        }

        $data = $this->payload();

        $this->assertSame(11, $data['count']);
        $this->assertCount(8, $data['orders']);
    }

    /**
     * The figure must not move with the dashboard's range picker — an order
     * stuck since March is stuck whether or not the last seven days are shown.
     */
    public function test_the_figure_ignores_the_dashboard_range(): void
    {
        $this->paidOrder(InventoryService::STALE_COMMITMENT_DAYS + 30);

        $widget = app(StaleCommitmentsWidget::class);

        $this->assertSame(
            $widget->data(DashboardRange::Today)['count'],
            $widget->data(DashboardRange::NinetyDays)['count'],
        );
    }

    /** It sits alongside Lunar's own low-stock card rather than replacing it. */
    public function test_it_is_registered_next_to_lunars_low_stock_card(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $keys = array_map(fn ($w) => $w->key(), app(PanelManager::class)->widgets()->for($staff));

        $this->assertContains('shop-stale-commitments', $keys);
        $this->assertContains('low-stock', $keys);
    }

    /** Order data, so it rides on the orders permission. */
    public function test_it_is_hidden_without_the_orders_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->assertFalse(app(StaleCommitmentsWidget::class)->visible($staff));

        $staff->givePermissionTo('sales:manage-orders');

        $this->assertTrue(app(StaleCommitmentsWidget::class)->visible($staff->fresh()));
    }
}
