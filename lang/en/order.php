<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Order status labels
    |--------------------------------------------------------------------------
    |
    | Lunar's `config/lunar/orders.php` carries English labels for only the four
    | statuses it ships with; `completed`, `refunded` and `cancelled` are used
    | here but missing there, so customers were shown the raw handle.
    |
    */

    'status' => [
        'awaiting-payment' => 'Awaiting payment',
        'payment-offline' => 'Cash on delivery',
        'payment-received' => 'Payment received',
        'dispatched' => 'Dispatched',
        'completed' => 'Completed',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ],

];
