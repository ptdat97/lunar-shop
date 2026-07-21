<?php

namespace Modules\Order\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Lunar\Models\Order;
use Modules\Theme\Services\LocaleService;

/**
 * Resolves an order's notification recipient and sends order mailables.
 * Centralised so every email uses the same recipient rule and no-email guard.
 */
class OrderMailer
{
    /**
     * Send a mailable to the order's contact email (no-op if none resolvable).
     * The mail is rendered in the customer's locale (the storefront locale that
     * was active when the order/event fired), so a queued mail keeps the right
     * language regardless of the worker's locale.
     */
    public function send(Order $order, Mailable $mailable): bool
    {
        $email = $this->recipient($order);

        if (! $email) {
            return false;
        }

        Mail::to($email)->locale($this->locale())->send($mailable);

        return true;
    }

    /**
     * The locale to render the email in: the currently active locale (the
     * customer's storefront locale at send time) when it's a supported one,
     * otherwise the store's configured default.
     */
    protected function locale(): string
    {
        $locales = app(LocaleService::class);
        $current = app()->getLocale();

        return $locales->isSupported($current) ? $current : $locales->default();
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
