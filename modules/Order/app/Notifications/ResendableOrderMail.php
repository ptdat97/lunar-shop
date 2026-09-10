<?php

namespace Modules\Order\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Lunar\Core\Models\Order;
use Modules\Theme\Services\LocaleService;

/**
 * Adapter that lets the panel resend one of the shop's own order emails.
 *
 * The panel has a "notify customer" action on every order
 * (`POST panel/orders/{order}/notify`). It builds whatever `NotifyCustomer`
 * finds in Lunar's OrderNotificationManifest, which shipped with exactly one
 * entry — Lunar's generic `order-update`. Our real order emails are Mailables
 * sent from event listeners, so they were not in the catalogue at all: staff
 * could send a customer a blank "order update", but could not resend the
 * confirmation email the customer was actually asking about.
 *
 * Lunar constructs a notification as `new $class($order, $message)`, so that is
 * the shape a subclass must keep. Each subclass names the Mailable it wraps;
 * nothing about the email itself is duplicated here.
 *
 * `$message` is the free-text note the admin types into the dialog. The shop's
 * mailables have no slot for one — their templates are fixed order documents —
 * so it is deliberately ignored rather than silently dropped into a subject
 * line. The panel still records it in the `email-notification` activity entry,
 * so what an admin typed is not lost.
 */
abstract class ResendableOrderMail extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?string $message = null,
    ) {}

    /**
     * The mailable this notification resends.
     */
    abstract protected function mailable(): Mailable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        // Same locale rule as OrderMailer: the active storefront locale when
        // it is one we support, else the store default. A resend happens from
        // the panel, where the active locale is the STAFF member's — without
        // this the customer would get their receipt in the admin's language.
        $locales = app(LocaleService::class);
        $current = app()->getLocale();

        return $this->mailable()->locale(
            $locales->isSupported($current) ? $current : $locales->default(),
        );
    }
}
