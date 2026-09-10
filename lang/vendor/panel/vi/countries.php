<?php

/* Việt hoá màn hình Quốc gia. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Quốc gia',
    'description' => 'Dữ liệu tra cứu dùng cho địa chỉ, vận chuyển và thuế.',
    'empty_title' => 'Không có quốc gia nào khớp',
    'search_placeholder' => 'Tìm quốc gia…',

    'column_iso2' => 'ISO-2',
    'column_iso3' => 'ISO-3',
    'column_name' => 'Tên',
    'column_states' => 'Tỉnh/thành',

    'field_iso2' => 'ISO-2',
    'field_iso3' => 'ISO-3',
    'field_name' => 'Tên',

    'section_details' => 'Chi tiết',
    'section_states' => 'Tỉnh/thành ({count})',
    'column_state_code' => 'Mã',
    'column_state_name' => 'Tên',
    'state_code_placeholder' => 'Mã',
    'state_name_placeholder' => 'Tên tỉnh/thành',
    'add_state' => 'Thêm',
    'remove_state' => 'Xoá tỉnh/thành',
    'empty_states_title' => 'Chưa có tỉnh/thành nào',

    'edit_title' => 'Sửa quốc gia — {name}',
    'confirm_delete_country' => 'Bạn chắc chắn muốn xoá quốc gia này?',
    'confirm_delete_title' => 'Xoá quốc gia?',
    'confirm_delete_body' => '{name} sẽ bị xoá vĩnh viễn.',
    'delete_blocked' => 'Quốc gia này đang được bản ghi khác tham chiếu nên không xoá được.',
    'delete_blocked_states' => 'Xoá hết tỉnh/thành trước thì mới xoá được quốc gia này.',
    'state_delete_blocked' => 'Không xoá được tỉnh/thành đang được một khu vực thuế tham chiếu.',

    'flash_updated' => 'Đã cập nhật quốc gia.',
    'flash_deleted' => 'Đã xoá quốc gia.',
    'flash_state_created' => 'Đã thêm tỉnh/thành.',
    'flash_state_deleted' => 'Đã xoá tỉnh/thành.',
];
