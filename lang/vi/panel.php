<?php

/*
 * Chuỗi khung cho màn hình admin riêng của shop trên Lunar panel.
 *
 * Nhãn của từng trường/cột vẫn lấy từ `admin.*` (đã có sẵn từ thời Filament) —
 * ở đây chỉ là phần khung mà engine resource dựng: nút, xác nhận, trạng thái rỗng.
 */

return [
    'section' => 'Nội dung shop',

    'new' => 'Thêm :name',
    'save' => 'Lưu',
    'back' => 'Quay lại',
    'edit' => 'Sửa',
    'delete' => 'Xoá',
    'confirm_delete' => 'Xoá mục này? Thao tác không hoàn tác được.',
    'search' => 'Tìm :name…',
    'empty' => 'Chưa có :name nào',

    'action_done' => 'Đã :name',

    'secret_kept' => 'Đã lưu — để trống nếu giữ nguyên.',

    'saved' => 'Đã lưu :name',
    'deleted' => 'Đã xoá :name',
    'invalid_json' => 'Nội dung không phải JSON hợp lệ.',
];
