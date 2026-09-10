<?php

/* Việt hoá phần URL slug. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Đường dẫn (slug)',
    'description' => '{count} slug · một slug mặc định cho các link không theo ngôn ngữ',

    'column_language' => 'Ngôn ngữ',
    'column_slug' => 'Slug & xem trước',
    'column_default' => 'Mặc định',
    'default_badge' => 'Mặc định',
    'set_default' => 'Đặt',

    'add_url' => 'Thêm đường dẫn',
    'add_description' => 'Thêm slug cho một ngôn ngữ bất kỳ. Mỗi bản ghi có thể mang nhiều slug cho cùng một ngôn ngữ — slug mặc định là link chính thức, số còn lại đóng vai trò link phụ hoặc link chuyển hướng.',
    'field_slug' => 'Slug',
    'make_default' => 'Đặt làm đường dẫn mặc định',
    'non_default_hint' => 'Slug không phải mặc định vẫn truy cập được trên storefront, hợp cho trang đã đổi tên hoặc link chiến dịch.',
    'add' => 'Thêm',
    'slug_placeholder' => 'ten-thuong-hieu',
    'remove' => 'Xoá slug',
    'empty' => 'Chưa có đường dẫn nào — thêm một slug để storefront định tuyến tới trang này.',

    'confirm_remove_title' => 'Xoá slug này?',
    'confirm_remove_body' => 'Các link đang dùng nó sẽ không truy cập được nữa. Slug mặc định tự chuyển sang slug kế tiếp.',

    'flash_created' => 'Đã thêm đường dẫn.',
    'flash_updated' => 'Đã cập nhật đường dẫn.',
    'flash_deleted' => 'Đã xoá đường dẫn.',
];
