<?php

/* Việt hoá màn hình Nhóm thuế. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Nhóm thuế',
    'description' => 'Gom các sản phẩm chịu cùng cách tính thuế vào một nhóm.',
    'create_tax_class' => 'Tạo nhóm thuế',
    'create_description' => 'Thêm một nhóm thuế để gán cho sản phẩm.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có nhóm thuế nào',

    'column_name' => 'Tên',
    'column_variants' => 'Biến thể',

    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Thuế suất tiêu chuẩn',

    'default_tax_class' => 'Nhóm thuế mặc định',
    'default_tax_class_hint' => 'Tự động áp cho sản phẩm mới.',
    'default_locked_hint' => 'Đây là nhóm thuế mặc định. Muốn đổi thì đặt một nhóm khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được nhóm thuế mặc định. Hãy đặt một nhóm khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',

    'edit_title' => 'Sửa nhóm thuế — {name}',
    'confirm_delete_tax_class' => 'Bạn chắc chắn muốn xoá nhóm thuế này?',
    'confirm_delete_title' => 'Xoá nhóm thuế?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được nhóm thuế đang gắn với biến thể sản phẩm.',
    'delete_blocked_default' => 'Không xoá được nhóm thuế mặc định. Hãy đặt một nhóm khác làm mặc định trước.',

    'flash_created' => 'Đã tạo nhóm thuế.',
    'flash_updated' => 'Đã cập nhật nhóm thuế.',
    'flash_deleted' => 'Đã xoá nhóm thuế.',
];
