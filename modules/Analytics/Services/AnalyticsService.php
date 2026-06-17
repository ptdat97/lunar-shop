<?php

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\Product;

class AnalyticsService
{
    /**
     * Get total revenue (paid orders).
     */
    public function totalRevenue(): float
    {
        return Order::whereIn('status', ['paid', 'completed'])
            ->sum(DB::raw('sub_total + shipping_total - discount_total'));
    }

    /**
     * Get total orders count.
     */
    public function totalOrders(): int
    {
        return Order::whereIn('status', ['paid', 'completed'])->count();
    }

    /**
     * Get total products.
     */
    public function totalProducts(): int
    {
        return Product::count();
    }

    /**
     * Get recent orders for dashboard.
     */
    public function recentOrders(int $limit = 5)
    {
        return Order::with(['customer', 'currency'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get monthly revenue summary.
     */
    public function monthlyRevenue(int $months = 6): array
    {
        $results = Order::whereIn('status', ['paid', 'completed'])
            ->where('created_at', '>=', now()->subMonths($months))
            ->select(
                DB::raw("strftime('%Y-%m', created_at) as month"),
                DB::raw('SUM(sub_total + shipping_total - discount_total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        return $results;
    }
}