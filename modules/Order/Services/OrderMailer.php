<?php

namespace Modules\Order\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Order;

/**
 * Resolves an order's notification recipient and sends order mailables.
 * Centralised so every email uses the same recipient rule and no-email guard.
 */
class OrderMailer
{
    /**
     * Send a mailable to the order's contact email (no-op if none resolvable).
     */
    public function send(Order $order, Mailable $mailable): bool
    {
        $email = $this->recipient($order);

        if (! $email) {
            return false;
        }

        Mail::to($email)->send($mailable);

        return true;
    }

    /**
     * Best-effort recipient: shipping/billing address contact email, then the
     * linked user's email.
     */
    public function recipient(Order $order): ?string
    {
        $order->loadMissing(['shippingAddress', 'billingAddress', 'user']);

        return $order->shippingAddress?->contact_email
            ?: $order->billingAddress?->contact_email
            ?: $order->user?->email;
    }
}
