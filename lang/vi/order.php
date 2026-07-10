<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nhãn trạng thái đơn hàng
    |--------------------------------------------------------------------------
    |
    | `config/lunar/orders.php` chỉ có nhãn tiếng Anh, và chỉ cho 4 status Lunar
    | ship sẵn; `completed`, `refunded`, `cancelled` được dùng trong code nhưng
    | không có ở đó, nên khách nhìn thấy handle thô.
    |
    */

    'status' => [
        'awaiting-payment' => 'Chờ thanh toán',
        'payment-offline' => 'Thanh toán khi nhận hàng',
        'payment-received' => 'Đã thanh toán',
        'dispatched' => 'Đang giao',
        'completed' => 'Hoàn thành',
        'refunded' => 'Đã hoàn tiền',
        'cancelled' => 'Đã huỷ',
    ],

];
