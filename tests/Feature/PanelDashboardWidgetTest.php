<?php

namespace Tests\Feature;

use Lunar\Core\Models\Staff;
use Lunar\Panel\Dashboard\DashboardRange;
use Lunar\Panel\PanelManager;
use Modules\Analytics\Panel\LifetimeWidget;
use Modules\Analytics\Services\AnalyticsService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The one dashboard card this shop adds.
 *
 * Lunar 2.0's panel already ships KPIs, a revenue chart, recent orders, top
 * products and low stock — everything the retired AnalyticsDashboard page had.
 * What it has no notion of is a horizon beyond 90 days, so that is the only
 * thing added here, and this test is as much about keeping it that way.
 */
class PanelDashboardWidgetTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_the_shop_contributes_only_what_lunar_lacks(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);

        $keys = array_map(
            fn ($widget) => $widget->key(),
            app(PanelManager::class)->widgets()->for($staff),
        );

        $ours = array_values(array_filter($keys, fn (string $key) => str_starts_with($key, 'shop-')));

        // Two, and each answers a question Lunar's own dashboard does not:
        //  - lifetime: a horizon longer than 90 days, which its ranges cap at.
        //  - stale commitments: stock held by orders that never ship, which its
        //    LowStockWidget (what is running out) says nothing about.
        // The assertion is exact so that rebuilding a first-party card here
        // fails rather than quietly duplicating upstream's work.
        sort($ours);

        $this->assertSame(['shop-lifetime', 'shop-stale-commitments'], $ours);

        // And they sit alongside the first-party cards rather than replacing them.
        foreach (['kpis', 'revenue-chart', 'recent-orders', 'top-products', 'low-stock'] as $firstParty) {
            $this->assertContains($firstParty, $keys);
        }
    }

    /**
     * The payload has to match what the panel's own components expect —
     * TimeSeriesChart reads `value` as major units and `display` as the tooltip
     * text, so sending minor units would render a chart a hundred times too
     * tall with the right numbers on it.
     */
    public function test_the_payload_matches_the_panels_chart_contract(): void
    {
        $widget = app(LifetimeWidget::class);

        $data = $widget->data(DashboardRange::ThirtyDays);

        $this->assertCount(4, $data['tiles']);
        $this->assertSame(6, count($data['points']), 'Thẻ này để nhìn 6 tháng.');

        foreach ($data['points'] as $point) {
            $this->assertArrayHasKey('label', $point);
            $this->assertArrayHasKey('value', $point);
            $this->assertArrayHasKey('display', $point);
            $this->assertIsFloat($point['value']);
        }
    }

    /**
     * The card shows lifetime figures, so it must ignore the dashboard's range
     * picker — that is the whole reason it exists next to the range-scoped
     * first-party cards.
     */
    public function test_the_figures_do_not_move_with_the_dashboard_range(): void
    {
        $widget = app(LifetimeWidget::class);

        $this->assertSame(
            $widget->data(DashboardRange::Today),
            $widget->data(DashboardRange::NinetyDays),
        );
    }

    /** Money formatting lives in the service so every reader agrees on it. */
    public function test_money_formatting_uses_the_default_currencys_decimals(): void
    {
        $this->seedBaseData();

        $analytics = app(AnalyticsService::class);

        $this->assertSame(1234.56, $analytics->major(123456));
        $this->assertStringContainsString('1,234.56', $analytics->format(123456));
    }

    /** Sales figures are gated by the orders permission. */
    public function test_the_widget_is_hidden_without_the_orders_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->assertFalse(app(LifetimeWidget::class)->visible($staff));

        $staff->givePermissionTo('sales:manage-orders');

        $this->assertTrue(app(LifetimeWidget::class)->visible($staff->fresh()));
    }
}
