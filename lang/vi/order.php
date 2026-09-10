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

    /*
    |--------------------------------------------------------------------------
    | Nhãn thông báo gửi lại được từ panel
    |--------------------------------------------------------------------------
    |
    | Hiện trong hộp thoại "Gửi thông báo" trên trang đơn hàng của panel. Khoá
    | được đăng ký vào OrderNotificationManifest ở OrderServiceProvider.
    |
    */

    'notifications' => [
        'confirmation' => 'Xác nhận đơn hàng',
        'paid' => 'Đã nhận thanh toán',
    ],
];
