<?php

namespace Modules\Analytics\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Modules\Analytics\Services\AnalyticsService;

/**
 * Admin sales dashboard: headline KPIs (revenue, orders, AOV, catalogue size),
 * a 6-month revenue trend, recent orders, and best-sellers. Read-only — all
 * figures come from AnalyticsService over Lunar's order data.
 */
class AnalyticsDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = -10;

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.sales');
    }

    protected static ?string $title = 'Sales Dashboard';

    protected static ?string $slug = 'sales-dashboard';

    protected static string $view = 'analytics::filament.pages.dashboard';

    /** @var array<string, mixed> */
    public array $stats = [];

    /** @var array<int, array{month:string, revenue:int, orders:int, label:string, formatted:string}> */
    public array $monthly = [];

    /** @var Collection<int, Order> */
    public $recent;

    /** @var array<int, array{name:string, quantity:int, revenue:int, formatted:string}> */
    public array $topProducts = [];

    public function mount(AnalyticsService $analytics): void
    {
        $this->stats = [
            'revenue' => $this->money($analytics->totalRevenue()),
            'orders' => Number::format($analytics->totalOrders()),
            'aov' => $this->money($analytics->averageOrderValue()),
            'products' => Number::format($analytics->totalProducts()),
        ];

        $this->monthly = array_map(function (array $row): array {
            $row['label'] = Carbon::createFromFormat('Y-m', $row['month'])->format('M Y');
            $row['formatted'] = $this->money($row['revenue']);

            return $row;
        }, $analytics->monthlyRevenue());

        $this->recent = $analytics->recentOrders();

        $this->topProducts = $analytics->topProducts()->map(function (array $row): array {
            $row['formatted'] = $this->money($row['revenue']);

            return $row;
        })->all();
    }

    /** Peak monthly revenue, used to scale the trend bars in the view. */
    public function peakRevenue(): int
    {
        return max(1, ...array_column($this->monthly, 'revenue'));
    }

    /**
     * Format minor units into the store's default currency.
     */
    protected function money(int $minor): string
    {
        $currency = Currency::getDefault();
        $decimals = $currency?->decimal_places ?? 2;
        $value = $minor / (10 ** $decimals);

        return Number::currency($value, $currency?->code ?? 'USD');
    }
}
