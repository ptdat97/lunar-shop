<?php

namespace Modules\Order\Services;

use Lunar\Models\Order;

class OrderService
{
    /**
     * A customer's orders, newest first.
     *
     * `$page` is explicit rather than left to the resolver's `?page=` sniffing,
     * so the caller controls it and the controller can clamp the input.
     */
    public function customerOrders(int $customerId, int $perPage = 20, int $page = 1)
    {
        return Order::where('customer_id', $customerId)
            ->with(['lines', 'currency', 'shippingAddress', 'billingAddress'])
            ->latest()
            ->paginate(perPage: $perPage, page: $page);
    }

    /**
     * Find an order by its public reference (e.g. the confirmation page).
     */
    public function findByReference(string $reference): ?Order
    {
        return Order::where('reference', $reference)
            ->with('lines')
            ->first();
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
