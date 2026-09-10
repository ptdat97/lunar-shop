<?php

namespace Modules\Analytics\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductVariant;
use Modules\Order\Support\OrderStatus;

/**
 * Sales reporting over Lunar's orders. Read-only aggregation — feeds the admin
 * dashboard widget and any reporting API.
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

    /**
     * Base query scoped to paid orders.
     *
     * Lunar 2.0 has no `status` column to filter on — "a real sale" is now a
     * predicate over placed_at / cancelled_at / payment_status, expressed once
     * in {@see OrderStatus::scopePaid()}.
     */
    protected function paidOrders(): Builder
    {
        return OrderStatus::scopePaid(Order::query());
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
        $variantMorph = (new ProductVariant)->getMorphClass();

        return OrderLine::query()
            ->select('purchasable_id')
            ->selectRaw('SUM(quantity) as units, SUM(total) as revenue')
            ->where('purchasable_type', $variantMorph)
            ->whereHas('order', fn (Builder $q) => OrderStatus::scopePaid($q))
            ->groupBy('purchasable_id')
            ->orderByDesc('units')
            ->limit($limit)
            ->get()
            ->map(function ($line) {
                $variant = ProductVariant::with('product')->find($line->purchasable_id);

                return [
                    'product_id' => $variant?->product?->id,
                    'name' => $variant?->getDescription() ?? 'Unknown',
                    'quantity' => (int) $line->units,
                    'revenue' => (int) $line->revenue,
                ];
            });
    }

    /**
     * Minor units as money in the default currency.
     *
     * Lives here rather than in whatever renders it: the admin dashboard and
     * any reporting endpoint must agree on the decimal places, and the old
     * dashboard page kept its own copy of this.
     */
    public function format(int $minor): string
    {
        return Number::currency($this->major($minor), Currency::getDefault()?->code ?? 'USD');
    }

    /** Minor units as a plain number in the currency's major unit — chart geometry. */
    public function major(int $minor): float
    {
        return $minor / (10 ** (Currency::getDefault()?->decimal_places ?? 2));
    }
}
