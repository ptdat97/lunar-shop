<?php

return [

    'order_status' => [
        'title' => 'Order :reference updated',
        'body' => 'Your order is now: :status.',
        // Kept short on purpose: an SMS is billed per 160-character segment.
        'sms' => 'Order :reference: :status. Thank you for shopping with us!',
    ],

];
