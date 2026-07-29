<?php

namespace Modules\Notification\Services;

use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Data\SmsMessage;
use Modules\Notification\Support\SmsSettings;
use Modules\Order\Support\OrderStatus;
use Modules\Theme\Services\LocaleService;

/**
 * Sends the order-status SMS.
 *
 * Reaches guests, which is the point: {@see OrderNotifier} needs a `User` row,
 * so a guest checkout gets nothing from it. The phone number comes off the
 * order's address, exactly the way {@see \Modules\Order\Services\OrderMailer}
 * resolves the email.
 *
 * Every message costs the shop money, so sending is opt-in per status rather
 * than on every transition.
 */
class OrderSmsNotifier
{
    public function __construct(
        protected SmsSender $sender,
    ) {}

    public function statusChanged(Order $order): bool
    {
        if (! SmsSettings::sendsOn($order->status)) {
            return false;
        }

        if (! SmsSettings::isConfigured()) {
            Log::warning('order sms skipped: gateway not configured', ['order' => $order->id]);

            return false;
        }

        $phone = $this->recipient($order);

        if (! $phone) {
            return false;
        }

        return $this->sender->send(new SmsMessage(
            to: $phone,
            body: $this->body($order),
        ));
    }

    /**
     * The message text, rendered in the customer's language.
     *
     * Built with `__(..., locale:)` rather than by switching the app locale:
     * this runs inside a queued listener, and mutating global locale there
     * leaks into whatever job the worker picks up next.
     */
    protected function body(Order $order): string
    {
        $locale = $this->locale();

        return __('notification.order_status.sms', [
            'reference' => $order->reference,
            'status' => OrderStatus::label($order->status),
        ], $locale);
    }

    protected function locale(): string
    {
        $locales = app(LocaleService::class);
        $current = app()->getLocale();

        return $locales->isSupported($current) ? $current : $locales->default();
    }

    /**
     * Best-effort phone: shipping then billing contact, mirroring OrderMailer's
     * recipient rule. Normalised to E.164 because gateways reject local formats.
     */
    public function recipient(Order $order): ?string
    {
        $order->loadMissing(['shippingAddress', 'billingAddress']);

        $phone = $order->shippingAddress?->contact_phone
            ?: $order->billingAddress?->contact_phone;

        return $phone ? $this->normalise($phone) : null;
    }

    /**
     * Vietnamese numbers are typed as `0912…` but gateways want `+84912…`.
     * Anything already in international form is left alone.
     */
    protected function normalise(string $phone): ?string
    {
        $digits = preg_replace('/[^\d+]/', '', $phone) ?? '';

        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        $countryCode = (string) config('notification.sms_country_code', '+84');

        // A leading 0 is the national trunk prefix; it is replaced by the
        // country code, not appended to it.
        if (str_starts_with($digits, '0')) {
            return $countryCode.substr($digits, 1);
        }

        return $digits !== '' ? $countryCode.$digits : null;
    }
}
