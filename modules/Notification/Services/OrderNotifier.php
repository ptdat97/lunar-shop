<?php

namespace Modules\Notification\Services;

use Lunar\Models\Order;
use Modules\Notification\Notifications\OrderStatusChanged;
use Modules\Order\Services\OrderMailer;
use Modules\Theme\Services\LocaleService;

/**
 * Raises in-app / push notifications for an order.
 *
 * Only a signed-in buyer can receive one: a notification is stored against a
 * `User` row, and a guest checkout has none. The transactional email is what
 * reaches guests, and it is unchanged — see {@see OrderMailer}.
 */
class OrderNotifier
{
    public function statusChanged(Order $order, string $previousStatus): bool
    {
        $user = $order->loadMissing('user')->user;

        if (! $user) {
            return false;
        }

        // Stamp the customer's language onto the queued notification, the same
        // way OrderMailer does, so the worker's locale cannot leak into it.
        $user->notify(
            (new OrderStatusChanged($order, $previousStatus))->locale($this->locale()),
        );

        return true;
    }

    /**
     * The locale to render in: the active storefront locale when supported,
     * otherwise the store default.
     */
    protected function locale(): string
    {
        $locales = app(LocaleService::class);
        $current = app()->getLocale();

        return $locales->isSupported($current) ? $current : $locales->default();
    }
}
