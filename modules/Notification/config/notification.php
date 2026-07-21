<?php

use Modules\Notification\Drivers\NullPushSender;

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

];
