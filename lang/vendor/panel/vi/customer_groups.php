<?php

/* Việt hoá màn hình Nhóm khách hàng. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Nhóm khách hàng',
    'description' => 'Chia khách hàng thành nhóm để áp giá, khuyến mãi và phạm vi danh mục khác nhau.',
    'create_group' => 'Tạo nhóm',
    'create_description' => 'Thêm một nhóm để xếp khách hàng vào.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có nhóm khách hàng nào',

    'column_name' => 'Tên',
    'column_handle' => 'Mã định danh',
    'column_customers' => 'Khách hàng',

    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Khách sỉ',
    'field_handle' => 'Mã định danh',
    'handle_hint' => 'chữ-thường-và-gạch-ngang',
    'handle_placeholder' => 'tự sinh',

    'default_group' => 'Nhóm mặc định',
    'default_group_hint' => 'Khách mới tự động vào nhóm này.',
    'default_locked_hint' => 'Đây là nhóm mặc định. Muốn đổi thì đặt một nhóm khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được nhóm khách hàng mặc định. Hãy đặt một nhóm khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',
    'section_state' => 'Trạng thái',

    'edit_title' => 'Sửa nhóm khách hàng — {name}',
    'confirm_delete_group' => 'Bạn chắc chắn muốn xoá nhóm khách hàng này?',
    'confirm_delete_title' => 'Xoá nhóm khách hàng?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được nhóm đang có khách hàng.',
    'delete_blocked_default' => 'Không xoá được nhóm khách hàng mặc định. Hãy đặt một nhóm khác làm mặc định trước.',

    'flash_created' => 'Đã tạo nhóm khách hàng.',
    'flash_updated' => 'Đã cập nhật nhóm khách hàng.',
    'flash_deleted' => 'Đã xoá nhóm khách hàng.',
];
