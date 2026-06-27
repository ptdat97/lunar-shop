<?php

namespace Tests\Feature;

use Lunar\Models\Currency;
use Lunar\Models\Order;
use Acme\Analytics\AnalyticsService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Analytics: sales aggregation over Lunar orders. Runs on MySQL (no SQLite-only
 * SQL) and only counts orders in a paid/fulfilled status.
 */
class AnalyticsTest extends TestCase
{
    use CreatesStorefrontData;

    private function order(int $total, string $status, ?\Illuminate\Support\Carbon $createdAt = null): Order
    {
        return Order::factory()->create([
            'channel_id' => \Lunar\Models\Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'status' => $status,
            'sub_total' => $total,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => $total,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_revenue_and_orders_count_only_paid_statuses(): void
    {
        $this->order(10000, 'payment-received');
        $this->order(5000, 'payment-offline');
        $this->order(99999, 'awaiting-payment'); // excluded

        $analytics = app(AnalyticsService::class);

        $this->assertSame(15000, $analytics->totalRevenue());
        $this->assertSame(2, $analytics->totalOrders());
        $this->assertSame(7500, $analytics->averageOrderValue());
    }

    public function test_average_order_value_is_zero_with_no_paid_orders(): void
    {
        $this->order(99999, 'awaiting-payment');

        $this->assertSame(0, app(AnalyticsService::class)->averageOrderValue());
    }

    public function test_monthly_revenue_buckets_by_month_and_fills_gaps(): void
    {
        $this->order(10000, 'payment-received', now());
        $this->order(20000, 'payment-received', now()->subMonths(2));

        $monthly = app(AnalyticsService::class)->monthlyRevenue(months: 6);

        $this->assertCount(6, $monthly);
        $this->assertSame(now()->subMonths(5)->format('Y-m'), $monthly[0]['month']);

        // This month holds 10000, two months ago holds 20000, the rest are 0.
        $thisMonth = collect($monthly)->firstWhere('month', now()->format('Y-m'));
        $twoAgo = collect($monthly)->firstWhere('month', now()->subMonths(2)->format('Y-m'));

        $this->assertSame(10000, $thisMonth['revenue']);
        $this->assertSame(1, $thisMonth['orders']);
        $this->assertSame(20000, $twoAgo['revenue']);
    }

    public function test_summary_respects_a_date_range(): void
    {
        $this->order(10000, 'payment-received', now());
        $this->order(20000, 'payment-received', now()->subMonths(3));

        $summary = app(AnalyticsService::class)->summary(from: now()->subMonth());

        $this->assertSame(10000, $summary['revenue']);
        $this->assertSame(1, $summary['orders']);
    }

    public function test_top_products_ranks_by_units_sold(): void
    {
        $product = $this->createProduct(['name' => 'Hot Tee', 'stock' => 100]);
        $variant = $product->variants->first();

        $order = $this->order(6000, 'payment-received');
        \Lunar\Models\OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'Hot Tee',
            'identifier' => $variant->sku,
            'unit_price' => 2000,
            'unit_quantity' => 1,
            'quantity' => 3,
            'sub_total' => 6000,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 6000,
        ]);

        $top = app(AnalyticsService::class)->topProducts();

        $this->assertSame('Hot Tee', $top->first()['name']);
        $this->assertSame(3, $top->first()['quantity']);
        $this->assertSame(6000, $top->first()['revenue']);
    }
}
