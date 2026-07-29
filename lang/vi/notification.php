<?php

return [

    'order_status' => [
        'title' => 'Đơn :reference đã cập nhật',
        'body' => 'Đơn hàng của bạn: :status.',
        // Kept short on purpose: an SMS is billed per 160-character segment,
        // and Vietnamese accents push a message into 70-character segments.
        'sms' => 'Don :reference: :status. Cam on ban da mua hang!',
    ],

];
