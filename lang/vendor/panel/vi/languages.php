<?php

/* Việt hoá màn hình Ngôn ngữ. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Ngôn ngữ',
    'description' => 'Các ngôn ngữ mà nội dung danh mục được dịch sang.',
    'create_language' => 'Thêm ngôn ngữ',
    'create_description' => 'Thêm một ngôn ngữ để dịch nội dung danh mục sang.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có ngôn ngữ nào',

    'column_code' => 'Mã',
    'column_name' => 'Tên',

    'field_code' => 'Mã',
    'code_hint' => 'chuẩn ISO 639-1',
    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Tiếng Việt',

    'default_language' => 'Ngôn ngữ mặc định',
    'default_language_hint' => 'Dùng khi chưa có bản dịch cho ngôn ngữ được yêu cầu.',
    'default_locked_hint' => 'Đây là ngôn ngữ mặc định. Muốn đổi thì đặt một ngôn ngữ khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được ngôn ngữ mặc định. Hãy đặt một ngôn ngữ khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',
    'section_state' => 'Trạng thái',

    'edit_title' => 'Sửa ngôn ngữ — {name}',
    'confirm_delete_language' => 'Bạn chắc chắn muốn xoá ngôn ngữ này?',
    'confirm_delete_title' => 'Xoá ngôn ngữ?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Không xoá được ngôn ngữ đang có URL gắn với nó.',
    'delete_blocked_default' => 'Không xoá được ngôn ngữ mặc định. Hãy đặt một ngôn ngữ khác làm mặc định trước.',

    'flash_created' => 'Đã thêm ngôn ngữ.',
    'flash_updated' => 'Đã cập nhật ngôn ngữ.',
    'flash_deleted' => 'Đã xoá ngôn ngữ.',
];
