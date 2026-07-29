<?php

namespace Modules\Notification\Support;

use Modules\Core\Support\Settings;

/**
 * SMS delivery settings, edited in Admin → Settings → Notifications.
 *
 * Unlike push — where the driver names a PHP class and so stays in config — an
 * SMS gateway's endpoint and credentials are pure data. A shop switching from
 * eSMS to SpeedSMS, or rotating a leaked api key, is a shop decision at shop
 * speed; it must not require a deploy.
 *
 * Reads fall through to config/env when a field was never saved, so an install
 * driven entirely by .env keeps working. @see \Modules\Core\Support\Settings
 */
class SmsSettings
{
    public static function enabled(): bool
    {
        return (bool) app(Settings::class)->get('notification.sms_enabled', false);
    }

    /**
     * Which order events send an SMS. SMS costs money per message, so this is
     * opt-in per event rather than "every transition", which is what push does.
     *
     * @return list<string>
     */
    public static function events(): array
    {
        $events = app(Settings::class)->get('notification.sms_events', []);

        return array_values(array_filter((array) $events, 'is_string'));
    }

    public static function sendsOn(string $status): bool
    {
        return self::enabled() && in_array($status, self::events(), true);
    }

    /**
     * The gateway's connection details, with every key guaranteed present so
     * callers never have to null-check a field name.
     *
     * @return array<string, string>
     */
    public static function gateway(): array
    {
        $settings = app(Settings::class);

        $defaults = [
            'endpoint' => '',
            'api_key' => '',
            'api_secret' => '',
            'sender' => '',
            'auth' => 'body',
            'api_key_field' => 'api_key',
            'api_secret_field' => 'secret',
            'to_field' => 'to',
            'body_field' => 'content',
            'sender_field' => 'brandname',
        ];

        // `sms_gateway`, not `sms`: config's `notification.sms` holds the driver
        // class map, and Settings::get() falls back to config for anything
        // unsaved — reading `sms` here would hand a fresh install the nested
        // `drivers` array where it expects flat credentials.
        $stored = (array) ($settings->get('notification.sms_gateway', []) ?: []);

        return array_map(
            static fn ($value) => (string) $value,
            array_replace($defaults, array_filter($stored, static fn ($v) => $v !== null && $v !== '')),
        );
    }

    public static function isConfigured(): bool
    {
        $gateway = self::gateway();

        return $gateway['endpoint'] !== '' && $gateway['api_key'] !== '';
    }
}
