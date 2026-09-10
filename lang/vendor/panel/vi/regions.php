<?php

/* Việt hoá màn hình Vùng bán hàng. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Vùng bán hàng',
    'description' => 'Ngữ cảnh storefront gắn kênh bán, tiền tệ, ngôn ngữ và khu vực thuế lại với nhau.',
    'create_region' => 'Tạo vùng bán hàng',
    'create_description' => 'Thêm một vùng; tinh chỉnh phạm vi của nó ở màn hình chỉnh sửa.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có vùng bán hàng nào',

    'column_name' => 'Tên',
    'column_channel' => 'Kênh bán',
    'column_currency' => 'Tiền tệ',
    'column_language' => 'Ngôn ngữ',
    'column_countries' => 'Quốc gia',

    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Việt Nam',
    'field_handle' => 'Mã định danh',
    'handle_hint' => 'chữ-thường-và-gạch-ngang',
    'field_channel' => 'Kênh bán',
    'field_currency' => 'Tiền tệ',
    'field_language' => 'Ngôn ngữ',
    'field_tax_zone' => 'Khu vực thuế',
    'no_tax_zone' => 'Không gắn khu vực thuế',
    'field_price_display' => 'Cách hiện giá',
    'price_display_hint' => 'chỉ ảnh hưởng cách hiển thị',
    'price_display_inherit' => 'Theo mặc định của cửa hàng',
    'price_display_inc' => 'Đã gồm thuế',
    'price_display_exc' => 'Chưa gồm thuế',

    'default_region' => 'Vùng mặc định',
    'default_region_hint' => 'Dùng khi một request không khớp vùng nào khác.',
    'default_locked_hint' => 'Đây là vùng mặc định. Muốn đổi thì đặt một vùng khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được vùng mặc định. Hãy đặt một vùng khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',
    'section_countries' => 'Quốc gia ({count})',
    'countries_desc' => 'Request từ những quốc gia này sẽ rơi vào vùng này.',
    'add_country_placeholder' => 'Thêm quốc gia…',
    'remove_country' => 'Xoá quốc gia',
    'no_countries' => 'Chưa có quốc gia nào.',
    'section_state' => 'Tỉnh/thành',

    'edit_title' => 'Sửa vùng bán hàng — {name}',
    'confirm_delete' => 'Bạn chắc chắn muốn xoá vùng bán hàng này?',
    'confirm_delete_title' => 'Xoá vùng bán hàng?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được vùng đã có lịch sử đơn hàng.',
    'delete_blocked_default' => 'Không xoá được vùng mặc định. Hãy đặt một vùng khác làm mặc định trước.',

    'flash_created' => 'Đã tạo vùng bán hàng.',
    'flash_updated' => 'Đã cập nhật vùng bán hàng.',
    'flash_deleted' => 'Đã xoá vùng bán hàng.',
];
