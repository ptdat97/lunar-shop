<?php

namespace Modules\Order\Services;

use Lunar\Core\Models\Order;

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
     * Find an order by its human-facing reference — the number a customer
     * quotes to support. NOT for URLs: `reference` is the zero-padded primary
     * key, so a URL keyed on it is enumerable. Use findPlacedByPublicId().
     */
    public function findByReference(string $reference): ?Order
    {
        return Order::where('reference', $reference)
            ->with('lines')
            ->first();
    }

    /**
     * Find a PLACED order by its public handle, for the confirmation page.
     *
     * `public_id` is a ULID that Lunar mints for exactly this — its own docblock
     * calls it "the outward-facing handle". The confirmation page used to key on
     * `reference`, which is the zero-padded primary key (`00000001`,
     * `00000002`, …): anyone could count upwards and read what every customer of
     * this shop had bought and what they paid.
     *
     * Draft orders are excluded because a draft is not a purchase. Lunar's
     * checkout guide is explicit: an order is only placed once `placed_at` has a
     * value, and a gateway that creates the draft then loses the shopper leaves
     * a row that never became anything. Showing that as "thank you for your
     * order" tells someone they bought something they did not.
     */
    public function findPlacedByPublicId(string $publicId): ?Order
    {
        return Order::where('public_id', $publicId)
            ->whereNotNull('placed_at')
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
