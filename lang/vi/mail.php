<?php

return [
    // Shared
    'greeting_hi' => 'Chào :name,',
    'greeting_fallback_name' => 'bạn',
    'view_order' => 'Xem đơn hàng',
    'thanks' => 'Cảm ơn,',
    'table_item' => 'Sản phẩm',
    'table_qty' => 'SL',
    'table_subtotal' => 'Thành tiền',
    'shipping_to' => 'Giao đến',

    // Order confirmation
    'confirmation' => [
        'subject' => 'Đã xác nhận đơn hàng — :reference',
        'heading' => 'Cảm ơn bạn đã đặt hàng!',
        'intro' => 'chúng tôi đã nhận được đơn hàng **:reference** của bạn.',
        'total' => 'Tổng cộng: :total',
    ],

    // Order paid
    'paid' => [
        'subject' => 'Đã nhận thanh toán — :reference',
        'heading' => 'Đã nhận thanh toán',
        'intro' => 'Chúng tôi đã nhận được thanh toán cho đơn hàng **:reference** — cảm ơn bạn!',
        'amount_paid' => 'Số tiền đã thanh toán: :total',
        'preparing' => 'Đơn hàng của bạn đang được chuẩn bị. Chúng tôi sẽ báo khi hàng được gửi đi.',
    ],

    // Order status updated
    'status' => [
        'subject' => 'Cập nhật đơn hàng — :reference',
        'heading' => 'Đơn hàng của bạn có cập nhật',
        'intro' => 'Đơn hàng **:reference** hiện đang ở trạng thái **:status**.',
        'status_line' => 'Trạng thái: :status',
    ],

    // Invoice PDF
    'invoice' => [
        'title' => 'Hóa đơn',
        'number' => 'Số hóa đơn',
        'date' => 'Ngày',
        'bill_to' => 'Thanh toán bởi',
        'ship_to' => 'Giao đến',
        'unit_price' => 'Đơn giá',
        'subtotal' => 'Tạm tính',
        'shipping' => 'Phí vận chuyển',
        'tax' => 'Thuế',
        'total' => 'Tổng cộng',
        'thank_you' => 'Cảm ơn bạn đã mua hàng!',
    ],
];
