<?php

/* Việt hoá màn hình Tiền tệ. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Tiền tệ',
    'description' => 'Các loại tiền cửa hàng dùng để niêm yết giá và giao dịch.',
    'create_currency' => 'Tạo loại tiền',
    'create_description' => 'Thêm một loại tiền mà cửa hàng có thể niêm yết giá và giao dịch.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có loại tiền nào',

    'column_code' => 'Mã',
    'column_name' => 'Tên',
    'column_exchange_rate' => 'Tỷ giá',

    'field_code' => 'Mã',
    'code_hint' => 'chuẩn ISO 4217',
    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Đồng Việt Nam',
    'field_exchange_rate' => 'Tỷ giá',
    'field_decimal_places' => 'Số chữ số thập phân',

    'default_currency' => 'Tiền tệ mặc định',
    'default_currency_hint' => 'Dùng cho sổ sách cửa hàng và giá mặc định trên storefront.',
    'default_locked_hint' => 'Đây là loại tiền mặc định. Muốn đổi thì đặt một loại tiền khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được loại tiền mặc định. Hãy đặt một loại tiền khác làm mặc định thay thế.',
    'default_disable_blocked' => 'Không tắt được loại tiền mặc định. Hãy đặt một loại tiền khác làm mặc định trước.',
    'enabled_hint' => 'Khi tắt, khách không giao dịch bằng loại tiền này được.',
    'enabled_locked_hint' => 'Loại tiền mặc định thì luôn bật.',

    'sync_prices' => 'Đồng bộ giá',
    'sync_prices_hint' => 'Sinh giá theo loại tiền này từ loại tiền mặc định, quy đổi theo tỷ giá.',

    'section_details' => 'Chi tiết',
    'section_state' => 'Trạng thái',

    'edit_title' => 'Sửa loại tiền — {code}',
    'confirm_delete_currency' => 'Bạn chắc chắn muốn xoá loại tiền này?',
    'confirm_delete_title' => 'Xoá loại tiền?',
    'confirm_delete_body' => '{code} sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được loại tiền đang có giá gắn với nó.',
    'delete_blocked_default' => 'Không xoá được loại tiền mặc định. Hãy đặt một loại tiền khác làm mặc định trước.',

    'flash_created' => 'Đã tạo loại tiền.',
    'flash_updated' => 'Đã cập nhật loại tiền.',
    'flash_deleted' => 'Đã xoá loại tiền.',
];
