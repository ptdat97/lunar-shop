<?php

namespace Modules\Order\Actions;

use Lunar\Core\Actions\Orders\NotifyCustomer;
use Lunar\Core\Models\Order;
use Modules\Order\Services\OrderMailer;

/**
 * Lunar's NotifyCustomer, with the shop's own recipient rule.
 *
 * Lunar resolves a recipient from the billing and shipping contact emails only.
 * That is one fallback short of what this shop needs: the API checkout accepts
 * a null `contact_email` (CheckoutController marks it `nullable`, unlike the
 * web PlaceOrderRequest which requires it), so a signed-in shopper ordering
 * through the app can leave an order whose only address is the account itself.
 *
 * Every automatic email already handles that — {@see OrderMailer::recipient()}
 * falls back to the linked user's address. Without this the panel's "notify
 * customer" dialog would be the one path that refuses to send, throwing
 * "no recipients" on an order the shop has already emailed successfully.
 *
 * Only the recipient set changes; the send, the `email-notification` activity
 * entry and the OrderCustomerNotified event stay Lunar's.
 */
class NotifyCustomerWithUserFallback extends NotifyCustomer
{
    /**
     * @param  array<int, string>  $recipients
     * @return array<int, string>
     */
    protected function resolveRecipients(Order $order, array $recipients): array
    {
        $resolved = parent::resolveRecipients($order, $recipients);

        if ($resolved !== []) {
            return $resolved;
        }

        // Explicit recipients that resolved to nothing are a caller error, not
        // a reason to substitute someone else's inbox.
        if ($recipients !== []) {
            return [];
        }

        return array_values(array_filter([
            app(OrderMailer::class)->recipient($order),
        ]));
    }
}
