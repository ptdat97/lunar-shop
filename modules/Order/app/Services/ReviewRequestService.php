<?php

namespace Modules\Order\Services;

use Illuminate\Support\Collection;
use Lunar\Core\Models\Order;
use Modules\Core\Support\Settings;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Support\OrderStatus;

/**
 * Decides which delivered orders are ready to be asked for a review.
 *
 * The shop has a review system and nothing that ever asks for a review, so the
 * only reviews it gets are from customers motivated enough to go looking. For
 * fashion that is a real loss: reviews are the closest a shopper gets to seeing
 * the item on someone else, and more reviews convert more shoppers into the
 * orders that produce more reviews.
 *
 * The judgement about WHEN to ask is the interesting part, and it is a shop
 * rule, so it lives here rather than in the command.
 */
class ReviewRequestService
{
    /** Wait this long after the goods shipped before asking. */
    public const DEFAULT_DELAY_DAYS = 7;

    /** Asking before it can plausibly have arrived reads as not paying attention. */
    public const MIN_DELAY_DAYS = 2;

    public const MAX_DELAY_DAYS = 60;

    /**
     * Stop asking about orders older than this. An email about something bought
     * three months ago is not a review request, it is a shop that lost track.
     */
    public const STALE_AFTER_DAYS = 90;

    public function __construct(protected Settings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('checkout.review_request_enabled', false);
    }

    public function delayDays(): int
    {
        $days = (int) $this->settings->get('checkout.review_request_days', self::DEFAULT_DELAY_DAYS);

        return max(self::MIN_DELAY_DAYS, min(self::MAX_DELAY_DAYS, $days));
    }

    /**
     * Orders whose goods shipped long enough ago to ask about.
     *
     * Anchored on `lunar_fulfilments.shipped_at`, not on the order's own
     * timestamps: the 2.0 upgrade dropped `orders.dispatched_at` and made the
     * lifecycle derived, so the moment the goods left the shop now lives on the
     * fulfilment. `orders.updated_at` would be wrong for a different reason —
     * any staff note or tag edit bumps it.
     *
     * @return Collection<int, Order>
     */
    public function dueForRequest(?int $days = null, int $limit = 100): Collection
    {
        $days ??= $this->delayDays();

        return Order::query()
            ->whereNull('review_requested_at')
            ->whereNotNull('placed_at')
            ->whereNull('cancelled_at')
            // Shipped, and long enough ago.
            ->whereHas('fulfilments', fn ($q) => $q
                ->whereNotNull('shipped_at')
                ->where('shipped_at', '<', now()->subDays($days))
                ->where('shipped_at', '>', now()->subDays(self::STALE_AFTER_DAYS)))
            ->with(['lines', 'shippingAddress', 'billingAddress', 'user'])
            ->orderBy('id')
            ->limit($limit)
            ->get()
            // Asking someone who returned the goods how they liked them is the
            // kind of email that loses a customer, not one that gains a review.
            ->reject(fn (Order $order) => $this->wasReturnedOrRefunded($order))
            ->filter(fn (Order $order) => app(OrderMailer::class)->recipient($order) !== null)
            ->values();
    }

    /**
     * A returned or refunded order must never get a review request.
     *
     * Checked on the derived status rather than a flag, because 2.0 has no
     * status column — `refunded` is inferred from the transaction ledger.
     */
    protected function wasReturnedOrRefunded(Order $order): bool
    {
        // Truy vấn thẳng chứ không qua quan hệ: Order là model của Lunar và
        // KHÔNG có chiều ngược `returnRequests` — ReturnRequest là bảng của
        // module này, chỉ belongsTo một chiều.
        return OrderStatus::of($order) === OrderStatus::REFUNDED
            || ReturnRequest::query()->where('order_id', $order->id)->exists();
    }

    /**
     * Recorded before the send, for the same reason as the abandoned-cart
     * sweep: losing one request beats sending two.
     */
    public function markRequested(Order $order): void
    {
        Order::withoutTimestamps(
            fn () => $order->forceFill(['review_requested_at' => now()])->saveQuietly(),
        );
    }
}
