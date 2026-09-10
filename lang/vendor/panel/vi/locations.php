<?php

/* Việt hoá màn hình Kho hàng. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Kho hàng',
    'description' => 'Những nơi chứa hàng và xuất hàng đi giao.',
    'create_location' => 'Tạo kho hàng',
    'create_description' => 'Thêm một nơi chứa hàng và xuất hàng đi giao.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có kho hàng nào',

    'column_name' => 'Tên',
    'column_handle' => 'Mã định danh',
    'column_stocked' => 'Biến thể có tồn',

    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Kho chính',
    'field_handle' => 'Mã định danh',
    'handle_hint' => 'chữ-thường-và-gạch-ngang',
    'handle_placeholder' => 'tự sinh',

    'default_location' => 'Kho mặc định',
    'default_location_hint' => 'Dùng khi một lần giao hàng không chỉ định kho cụ thể.',
    'default_locked_hint' => 'Đây là kho mặc định. Muốn đổi thì đặt một kho khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được kho mặc định. Hãy đặt một kho khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',
    'section_state' => 'Trạng thái',

    'edit_title' => 'Sửa kho hàng — {name}',
    'confirm_delete' => 'Bạn chắc chắn muốn xoá kho hàng này?',
    'confirm_delete_title' => 'Xoá kho hàng?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được kho đã có lần giao hàng hoặc lịch sử tồn kho.',
    'delete_blocked_default' => 'Không xoá được kho mặc định. Hãy đặt một kho khác làm mặc định trước.',

    'flash_created' => 'Đã tạo kho hàng.',
    'flash_updated' => 'Đã cập nhật kho hàng.',
    'flash_deleted' => 'Đã xoá kho hàng.',
];
