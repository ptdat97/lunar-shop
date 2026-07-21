<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paid order statuses
    |--------------------------------------------------------------------------
    |
    | Order statuses that count as realised revenue in the sales dashboard.
    | Should mirror the "paid"/"fulfilled" statuses configured in
    | config/lunar/orders.php. Orders awaiting payment, cancelled, or refunded
    | are excluded.
    |
    */
    'paid_statuses' => [
        'payment-offline',
        'payment-received',
        'dispatched',
        'completed',
    ],

];
