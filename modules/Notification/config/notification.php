<?php

use Modules\Notification\Drivers\HttpSmsSender;
use Modules\Notification\Drivers\NullPushSender;
use Modules\Notification\Drivers\NullSmsSender;

return [

    /*
    |--------------------------------------------------------------------------
    | Push delivery
    |--------------------------------------------------------------------------
    |
    | The default driver delivers nothing and logs what it would have sent. No
    | provider is wired yet: shipping an FCM integration before there is an app
    | to receive it would be an abstraction with nothing behind it.
    |
    | Adding one means writing a driver against Modules\Notification\Contracts\
    | PushSender and naming it here — no caller changes.
    |
    */

    'push' => [
        'driver' => env('PUSH_DRIVER', 'null'),

        'drivers' => [
            'null' => NullPushSender::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS delivery
    |--------------------------------------------------------------------------
    |
    | Same driver pattern as push, and for the same reason: the driver names a
    | PHP class, is resolved before the database is reachable, and naming one
    | that isn't installed would break every request. That stays here.
    |
    | The gateway's *credentials* do not — an endpoint and an api key are data,
    | and rotating a leaked key must not need a deploy. Those are edited in
    | Admin → Settings → Notifications and read via SmsSettings.
    |
    | `http` is a generic gateway driver: it reads the provider's field names
    | from settings, which covers the common Vietnamese providers (eSMS,
    | SpeedSMS, VNPT) without a class each. A provider needing real request
    | signing gets its own driver, named here.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'null'),

        'drivers' => [
            'null' => NullSmsSender::class,
            'http' => HttpSmsSender::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push kill-switch
    |--------------------------------------------------------------------------
    |
    | The default only. The live value is edited in Admin → Settings →
    | Notifications and read through Modules\Notification\Support\PushSettings,
    | so an operator can silence a misbehaving provider without a deploy.
    |
    | Flat rather than nested under `push` because a Settings group is one level
    | deep: `Settings::get('notification.push_enabled')` reads group
    | `notification`, key `push_enabled`.
    |
    */

    'push_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | SMS defaults
    |--------------------------------------------------------------------------
    |
    | Defaults only — the live values live in app_settings. Off by default
    | because every message costs the shop money; an operator opts in, picks
    | which order statuses are worth paying for, and supplies the gateway.
    |
    | Flat keys for the same reason as `push_enabled`: a Settings group is one
    | level deep.
    |
    */

    'sms_enabled' => false,

    'sms_events' => [],

    // Trunk-prefix numbers ("0912…") are rewritten to this country code before
    // being handed to a gateway, which want E.164.
    'sms_country_code' => env('SMS_COUNTRY_CODE', '+84'),

    /*
    |--------------------------------------------------------------------------
    | Admin SMTP override
    |--------------------------------------------------------------------------
    |
    | Off by default: a fresh install sends mail using .env, which is what a
    | deployed app expects. Turning this on in the admin lets a shop owner point
    | the store at their own SMTP host without shell access; MailSettings::apply()
    | then pushes those values into the runtime mail config at boot.
    |
    */

    'mail_override' => false,

];
