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

];
