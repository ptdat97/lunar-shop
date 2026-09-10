<?php

/* Việt hoá màn hình Vai trò. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Vai trò',
    'description' => 'Gom quyền thành các vai trò để gán cho nhân viên.',
    'create_role' => 'Tạo vai trò',
    'create_description' => 'Thêm một vai trò; cấp quyền cho nó ở màn hình chỉnh sửa.',
    'first_party_badge' => 'Có sẵn',
    'empty_title' => 'Chưa có vai trò nào',

    'column_name' => 'Tên',
    'column_permissions' => 'Quyền',
    'column_staff' => 'Nhân viên',

    'field_name' => 'Tên',
    'name_hint' => 'chữ-thường-và-gạch-ngang',
    'name_placeholder' => 'ví dụ: quan-ly-danh-muc',

    'section_permissions' => 'Quyền',
    'permissions_desc' => 'Những việc nhân viên mang vai trò này làm được. Tài khoản quản trị luôn có mọi quyền, bất kể vai trò.',

    'edit_title' => 'Sửa vai trò — {name}',
    'confirm_delete' => 'Bạn chắc chắn muốn xoá vai trò này?',
    'confirm_delete_title' => 'Xoá vai trò?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked_first_party' => 'Không xoá được vai trò có sẵn của hệ thống.',
    'delete_blocked_staff' => 'Không xoá được vai trò đang có nhân viên nắm giữ. Hãy gỡ vai trò khỏi họ trước.',

    'flash_created' => 'Đã tạo vai trò.',
    'flash_updated' => 'Đã cập nhật vai trò.',
    'flash_deleted' => 'Đã xoá vai trò.',
];
