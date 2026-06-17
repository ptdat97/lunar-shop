<?php

namespace Modules\Order\Services;

use Lunar\Models\Order;

class OrderService
{
    /**
     * Get orders for a customer.
     */
    public function customerOrders(int $customerId, int $perPage = 20)
    {
        return Order::where('customer_id', $customerId)
            ->with(['lines', 'currency', 'shippingAddress', 'billingAddress'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Find an order by ID for a customer.
     */
    public function findForCustomer(int $orderId, int $customerId): ?Order
    {
        return Order::where('id', $orderId)
            ->where('customer_id', $customerId)
            ->with(['lines', 'currency', 'shippingAddress', 'billingAddress', 'transactions'])
            ->first();
    }

    /**
     * Get latest orders (admin).
     */
    public function latest(int $limit = 10)
    {
        return Order::with(['customer', 'currency'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}