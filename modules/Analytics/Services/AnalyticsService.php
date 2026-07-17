<?php

namespace Modules\Analytics\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Modules\Catalog\Models\ProductSku;
use Modules\Order\Support\OrderStatus;

/**
 * Sales reporting over Lunar's orders. Read-only aggregation — feeds the admin
 * dashboard (AnalyticsDashboard) and any reporting API.
 *
 * "Revenue" counts orders in a paid/fulfilled status (see {@see paidStatuses()})
 * and uses the order `total` (minor units), matching what the customer actually
 * paid. SQL is portable (no SQLite-only date functions) so it runs on MySQL in
 * production and in tests.
 */
class AnalyticsService
{
    /**
     * Order statuses that count as realised revenue. Anything still awaiting
     * payment (or cancelled/refunded) is excluded.
     *
     * @return array<int, string>
     */
    public function paidStatuses(): array
    {
        // Delegates to the order lifecycle rather than re-stating it: "paid"
        // must mean the same thing here, to membership spend, and to the
        // recommendations built from purchase history.
        return OrderStatus::paid();
    }

    /** Base query scoped to paid orders. */
    protected function paidOrders(): Builder
    {
        return Order::query()->whereIn('status', $this->paidStatuses());
    }

    /**
     * Total realised revenue (minor units) across all paid orders.
     */
    public function totalRevenue(): int
    {
        return (int) $this->paidOrders()->sum('total');
    }

    /**
     * Count of paid orders.
     */
    public function totalOrders(): int
    {
        return $this->paidOrders()->count();
    }

    /**
     * Average order value (minor units), 0 when there are no paid orders.
     */
    public function averageOrderValue(): int
    {
        $count = $this->totalOrders();

        return $count > 0 ? intdiv($this->totalRevenue(), $count) : 0;
    }

    /**
     * Total products in the catalogue (a quick catalogue-size KPI).
     */
    public function totalProducts(): int
    {
        return Product::count();
    }

    /**
     * Revenue + order count within a date range (defaults to all time).
     *
     * @return array{revenue:int, orders:int}
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = $this->paidOrders();

        if ($from) {
            $query->where('created_at', '>=', $from);
        }
        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $row = $query->selectRaw('COALESCE(SUM(total), 0) as revenue, COUNT(*) as orders')->first();

        return [
            'revenue' => (int) ($row->revenue ?? 0),
            'orders' => (int) ($row->orders ?? 0),
        ];
    }

    /**
     * Recent orders for the dashboard list.
     *
     * @return Collection<int, Order>
     */
    public function recentOrders(int $limit = 8): Collection
    {
        return Order::with(['customer', 'currency'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Revenue + order count per month for the last $months (oldest first).
     * Portable across MySQL/SQLite by bucketing in PHP rather than in SQL.
     *
     * @return array<int, array{month:string, revenue:int, orders:int}>
     */
    public function monthlyRevenue(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $orders = $this->paidOrders()
            ->where('created_at', '>=', $start)
            ->get(['total', 'created_at']);

        // Pre-fill every month in range with zeros so gaps render as 0, not holes.
        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $key = $start->copy()->addMonths($i)->format('Y-m');
            $buckets[$key] = ['month' => $key, 'revenue' => 0, 'orders' => 0];
        }

        foreach ($orders as $order) {
            $key = $order->created_at->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }
            // `total` is cast to a Price object on the model — use the raw
            // integer (minor units) from the DB.
            $buckets[$key]['revenue'] += (int) $order->getRawOriginal('total');
            $buckets[$key]['orders']++;
        }

        return array_values($buckets);
    }

    /**
     * Best-selling products by units sold within paid orders.
     *
     * @return Collection<int, array{product_id:int|null, name:string, quantity:int, revenue:int}>
     */
    public function topProducts(int $limit = 5): Collection
    {
        $skuMorph = (new ProductSku)->getMorphClass();

        return OrderLine::query()
            ->select('purchasable_id')
            ->selectRaw('SUM(quantity) as units, SUM(total) as revenue')
            ->where('purchasable_type', $skuMorph)
            ->whereHas('order', fn (Builder $q) => $q->whereIn('status', $this->paidStatuses()))
            ->groupBy('purchasable_id')
            ->orderByDesc('units')
            ->limit($limit)
            ->get()
            ->map(function ($line) {
                $sku = ProductSku::with('product')->find($line->purchasable_id);

                return [
                    'product_id' => $sku?->product?->id,
                    'name' => $sku?->getDescription() ?? 'Unknown',
                    'quantity' => (int) $line->units,
                    'revenue' => (int) $line->revenue,
                ];
            });
    }
}
