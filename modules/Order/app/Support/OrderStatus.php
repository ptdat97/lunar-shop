<?php

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Builder;
use Lunar\Core\Models\Order;
use Lunar\Core\States\Order\Fulfilment\Fulfilled;
use Lunar\Core\States\Order\Fulfilment\PartiallyFulfilled;
use Lunar\Core\States\Order\Payment\Paid;
use Lunar\Core\States\Order\Payment\PartiallyPaid;
use Lunar\Core\States\Order\Payment\PartiallyRefunded;
use Lunar\Core\States\Order\Payment\Refunded;

/**
 * The shop's order lifecycle — the seven handles a customer is shown, and which
 * of them count as a real sale.
 *
 * One place, because the alternative already bit us. "Paid" used to be spelled
 * out separately in `analytics.paid_statuses` and `promotion.membership
 * .paid_statuses`; the two drifted, COD counted as revenue but never toward a
 * membership tier, and the fallback array behind the config key was copy-pasted
 * into five services that could each have been edited alone.
 *
 * ## Since Lunar 2.0 this is DERIVED, not stored
 *
 * 2.0 deleted `lunar_orders.status`. It deliberately does not model a
 * hand-driven headline lifecycle at all — stores disagree about what the steps
 * are — and replaces it with facts the core can compute for itself:
 *
 *   payment_status     rollup of the transaction ledger
 *   fulfilment_status  rollup of the fulfilment records
 *   cancelled_at       the order was called off
 *   closed_at          the order is archived / dealt with
 *
 * So the seven handles stay (the customer-facing vocabulary, the lang files,
 * the emails and the SMS all speak them) but they are now a *view* over those
 * four facts rather than a column anyone writes. Nothing sets a status any
 * more: record the fact — a transaction, a fulfilment, `cancel()`, `close()` —
 * and the status follows. That removes the class of bug where the column and
 * the ledger disagreed.
 *
 * The one distinction the four facts cannot make on their own is
 * `awaiting-payment` vs `payment-offline`: both are payment-pending, and what
 * separates them is HOW the order is meant to be paid, which lives on the order
 * as `meta.payment_type`. Hence {@see self::isPaidOnDelivery()}.
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
     * Payment types that mean "the customer pays on delivery".
     *
     * An order on one of these is a real sale the moment it is placed, even
     * though no money has moved and `payment_status` is therefore still
     * pending. Anything else pending is an order nobody has paid yet — a
     * gateway the shopper abandoned, or a bank transfer still in the post.
     *
     * Read from `lunar.payments.types.*.authorized`, which already declares
     * exactly this per type (`cod` → payment-offline, `bank-transfer` and the
     * gateways → awaiting-payment). Listing them again here would be a second
     * place describing one truth, and adding a payment method would silently
     * mis-book its revenue until someone remembered to edit both.
     *
     * @return list<string>
     */
    public static function offlinePaymentTypes(): array
    {
        return collect(config('lunar.payments.types', []))
            ->filter(fn ($config) => ($config['authorized'] ?? null) === self::PAYMENT_OFFLINE)
            ->keys()
            ->map(fn ($type) => (string) $type)
            ->values()
            ->all();
    }

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

    /**
     * The order's lifecycle handle, derived from the 2.0 facts.
     *
     * Read most-final-first: a cancelled order is cancelled whatever else its
     * ledger says, and a fully refunded one is refunded even if it shipped.
     * `closed_at` means somebody archived it, which is what `completed` always
     * meant here.
     *
     * A PARTIAL refund is deliberately not `refunded`: the sale still stands and
     * the customer keeps most of the goods, so the order stays where it was.
     * Under the old single column that nuance had nowhere to live and any refund
     * closed the order.
     *
     * `voided` is deliberately absent too. It means the order has transactions
     * but none of them succeeded — a declined gateway callback — which leaves
     * the customer exactly where they were: still owing the money. So it falls
     * through to `awaiting-payment`, as it did under the old column.
     */
    public static function of(?Order $order): ?string
    {
        if (! $order) {
            return null;
        }

        return match (true) {
            $order->isCancelled() => self::CANCELLED,
            $order->payment_status instanceof Refunded => self::REFUNDED,
            $order->isClosed() => self::COMPLETED,
            self::hasBeenHandedOver($order) => self::DISPATCHED,
            $order->payment_status instanceof Paid,
            $order->payment_status instanceof PartiallyPaid,
            $order->payment_status instanceof PartiallyRefunded => self::PAYMENT_RECEIVED,
            self::isPaidOnDelivery($order) => self::PAYMENT_OFFLINE,
            default => self::AWAITING_PAYMENT,
        };
    }

    /**
     * Have the goods actually left the shop?
     *
     * The rollup alone is not enough: `ResolveFulfilmentStatus` reports
     * `Fulfilled` for an order with NOTHING to fulfil — settled by definition —
     * so an order with no fulfillable lines would read as dispatched from the
     * moment it was created. Nothing to send is not the same as sent, so
     * require that there was something to hand over.
     */
    protected static function hasBeenHandedOver(Order $order): bool
    {
        $rolledUp = $order->fulfilment_status instanceof Fulfilled
            || $order->fulfilment_status instanceof PartiallyFulfilled;

        return $rolledUp && $order->fulfillableLines()->exists();
    }

    /**
     * Is this order meant to be paid when it arrives, rather than up front?
     *
     * Explicit membership, not "anything that isn't a gateway": a bank transfer
     * is also payment-pending with no gateway involved, but the money has NOT
     * been earned — treating it as a sale would book revenue for an order that
     * may never be paid. Checkout records `meta.payment_type` for every order so
     * this can never be a guess.
     */
    public static function isPaidOnDelivery(Order $order): bool
    {
        $type = data_get($order->meta, 'payment_type');

        return $type !== null && in_array((string) $type, self::offlinePaymentTypes(), true);
    }

    /** May the customer open a return against this order? */
    public static function isReturnable(?Order $order): bool
    {
        return in_array(self::of($order), self::RETURNABLE, true);
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
    public static function isClosed(?Order $order): bool
    {
        return in_array(self::of($order), self::CLOSED, true);
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

    /** Does this order count as a real sale? */
    public static function isPaid(?Order $order): bool
    {
        return in_array(self::of($order), static::paid(), true);
    }

    /**
     * Constrain a query to orders that count as a real sale.
     *
     * The old `whereIn('status', paid())` has no column to point at any more, so
     * the same rule is expressed against the 2.0 facts. Read it as: placed, not
     * called off, not given back, and either the money arrived or it is a COD
     * order where the money is owed.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public static function scopePaid(Builder $query, string $table = 'lunar_orders'): Builder
    {
        return $query
            ->whereNotNull("{$table}.placed_at")
            ->whereNull("{$table}.cancelled_at")
            ->whereNotIn("{$table}.payment_status", ['refunded', 'voided'])
            ->where(function (Builder $paid) use ($table) {
                $paid->where("{$table}.payment_status", '!=', 'pending')
                    ->orWhereRaw(self::offlineSql($table));
            });
    }

    /**
     * The `isPaidOnDelivery()` rule in SQL, for the callers that cannot use the
     * scope (FitHistoryService and CoPurchaseStrategy join orders into raw
     * aggregates).
     */
    public static function offlineSql(string $table = 'lunar_orders'): string
    {
        $offline = self::offlinePaymentTypes();

        if ($offline === []) {
            return '(1 = 0)';
        }

        $types = collect($offline)
            ->map(fn (string $type) => "'".addslashes($type)."'")
            ->implode(', ');

        $type = "JSON_UNQUOTE(JSON_EXTRACT({$table}.meta, '$.payment_type'))";

        return "({$type} IN ({$types}))";
    }

    /**
     * The whole "real sale" rule as raw SQL, for a query that cannot take the
     * scope. Keeps {@see self::scopePaid()} and this from drifting apart by
     * building both from the same pieces.
     */
    public static function paidSql(string $table = 'lunar_orders'): string
    {
        return "{$table}.placed_at IS NOT NULL"
            ." AND {$table}.cancelled_at IS NULL"
            ." AND {$table}.payment_status NOT IN ('refunded', 'voided')"
            ." AND ({$table}.payment_status <> 'pending' OR ".self::offlineSql($table).')';
    }

    /**
     * The localised label for a status handle.
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

    /** The localised label for an order, derived. */
    public static function labelFor(?Order $order): string
    {
        return self::label(self::of($order));
    }
}
