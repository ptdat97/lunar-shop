<?php

namespace Modules\Analytics\Panel;

use Illuminate\Support\Carbon;
use Lunar\Panel\Dashboard\DashboardRange;
use Lunar\Panel\Dashboard\Widget;
use Lunar\Panel\Dashboard\WidgetSpan;
use Lunar\Panel\Support\Position;
use Modules\Analytics\Services\AnalyticsService;

/**
 * The long view: lifetime totals and a six-month revenue trend.
 *
 * Deliberately the *only* thing this shop adds to the dashboard. Lunar 2.0's
 * panel already ships revenue/orders/AOV KPIs, a revenue chart, recent orders,
 * top products and low stock — all of which the old AnalyticsDashboard page
 * also had, so rebuilding them would have been duplicating first-party work.
 *
 * What the panel does not have is a horizon longer than 90 days: its ranges cap
 * there and it buckets by day. A fashion shop with seasonal collections needs
 * to see the season, so that gap is what this fills.
 *
 * `$range` is ignored on purpose — the point of this card is the figures that
 * do not move when the dashboard's range picker does.
 */
class LifetimeWidget extends Widget
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function key(): string
    {
        return 'shop-lifetime';
    }

    public function component(): string
    {
        return 'shop::LifetimeWidget';
    }

    public function label(): string
    {
        return __('admin.analytics.title');
    }

    public function icon(): ?string
    {
        return 'chart';
    }

    public function span(): WidgetSpan
    {
        return WidgetSpan::Full;
    }

    public function permission(): ?string
    {
        return 'sales:manage-orders';
    }

    public function position(): Position
    {
        // After the first-party cards: this is context, not the daily read.
        return Position::priority(80);
    }

    public function data(DashboardRange $range): array
    {
        return [
            'heading' => __('admin.analytics.lifetime'),
            'trendHeading' => __('admin.analytics.trend'),
            'tiles' => [
                [
                    'label' => __('admin.analytics.revenue'),
                    'value' => $this->analytics->format($this->analytics->totalRevenue()),
                    'icon' => 'chart',
                    'tone' => 'sage',
                ],
                [
                    'label' => __('admin.analytics.orders'),
                    'value' => (string) $this->analytics->totalOrders(),
                    'icon' => 'cart',
                    'tone' => 'neutral',
                ],
                [
                    'label' => __('admin.analytics.aov'),
                    'value' => $this->analytics->format($this->analytics->averageOrderValue()),
                    'icon' => 'percent',
                    'tone' => 'neutral',
                ],
                [
                    'label' => __('admin.analytics.products'),
                    'value' => (string) $this->analytics->totalProducts(),
                    'icon' => 'box',
                    'tone' => 'neutral',
                ],
            ],
            // TimeSeriesChart's ChartPoint contract: `value` drives the geometry
            // and must be in major units, `display` is what the tooltip shows.
            'points' => array_map(fn (array $row): array => [
                'label' => Carbon::createFromFormat('Y-m', $row['month'])->translatedFormat('M Y'),
                'value' => $this->analytics->major($row['revenue']),
                'display' => $this->analytics->format($row['revenue']),
            ], $this->analytics->monthlyRevenue()),
        ];
    }
}
