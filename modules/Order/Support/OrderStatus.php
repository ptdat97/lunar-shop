<?php

namespace Modules\Order\Support;

/**
 * The order lifecycle: status handles, their human labels, and which of them
 * count as "paid".
 *
 * One place, because the alternative already bit us. "Paid" used to be spelled
 * out separately in `analytics.paid_statuses` and `promotion.membership
 * .paid_statuses`; the two drifted, COD counted as revenue but never toward a
 * membership tier, and the fallback array behind the config key was copy-pasted
 * into five services that could each have been edited alone.
 */
class OrderStatus
{
    /** Placed, waiting for the customer to pay a gateway (or transfer money). */
    public const AWAITING_PAYMENT = 'awaiting-payment';

    /** Cash on delivery: owed on arrival, but the sale is real. */
    public const PAYMENT_OFFLINE = 'payment-offline';

    /** A gateway captured the money. */
    public const PAYMENT_RECEIVED = 'payment-received';

    public const DISPATCHED = 'dispatched';

    public const COMPLETED = 'completed';

    public const REFUNDED = 'refunded';

    public const CANCELLED = 'cancelled';

    /**
     * Statuses that count as a real sale — realised revenue, lifetime spend,
     * "products bought together", fit history.
     *
     * `payment-offline` (COD) belongs here: the goods left the shop and the
     * money is owed. The order is not paid *yet*, but it is sold.
     *
     * @var list<string>
     */
    public const PAID = [
        self::PAYMENT_OFFLINE,
        self::PAYMENT_RECEIVED,
        self::DISPATCHED,
        self::COMPLETED,
    ];

    /** Statuses where the goods are not going out, or are coming back. */
    public const CLOSED = [
        self::CANCELLED,
        self::REFUNDED,
    ];

    /**
     * Statuses from which a customer may open a return.
     *
     * Not the same as {@see self::PAID}: a COD order still in the courier's van
     * (`payment-offline`) has nothing to send back yet.
     *
     * @var list<string>
     */
    public const RETURNABLE = [
        self::PAYMENT_RECEIVED,
        self::DISPATCHED,
        self::COMPLETED,
    ];

    /** May the customer open a return against an order in this status? */
    public static function isReturnable(?string $status): bool
    {
        return $status !== null && in_array($status, self::RETURNABLE, true);
    }

    /**
     * Is this order finished with, such that a payment can no longer land on it?
     *
     * A cancelled or refunded order has already given its reserved stock back
     * (Inventory listens for `OrderStatusUpdated` and releases it). Letting a
     * late gateway callback flip it to `payment-received` would sell units that
     * are no longer held — and `stock_released_at` is already stamped, so they
     * would never be reserved again.
     */
    public static function isClosed(?string $status): bool
    {
        return $status !== null && in_array($status, self::CLOSED, true);
    }

    /**
     * The statuses that count as paid, honouring the admin-tunable config key
     * but never depending on it being present.
     *
     * @return list<string>
     */
    public static function paid(): array
    {
        return (array) config('analytics.paid_statuses', self::PAID);
    }

    /** Does this status count as a real sale? */
    public static function isPaid(?string $status): bool
    {
        return $status !== null && in_array($status, static::paid(), true);
    }

    /**
     * The localised label for a status handle.
     *
     * `config('lunar.orders.statuses')` carries English labels only, and only
     * for the four statuses Lunar ships with — `completed`, `refunded` and
     * `cancelled` are used throughout this codebase but absent from it, so
     * anything reading that config showed the raw handle to the customer.
     *
     * Resolution order: our translation file → Lunar's config label → the handle.
     */
    public static function label(?string $status): string
    {
        if (! $status) {
            return '';
        }

        $key = "order.status.{$status}";
        $translated = __($key);

        if ($translated !== $key) {
            return $translated;
        }

        return (string) config("lunar.orders.statuses.{$status}.label", $status);
    }
}
