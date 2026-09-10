<?php

/* Việt hoá màn hình Loại sản phẩm. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Loại sản phẩm',
    'description' => 'Khai các khuôn mà sản phẩm dựa trên. Mỗi loại quy định sản phẩm và biến thể của nó mang những thuộc tính nào, và nhân viên phải điền gì khi tạo mới.',
    'new_product_type' => 'Thêm loại sản phẩm',
    'create_title' => 'Thêm loại sản phẩm',
    'create_description' => 'Đặt tên cho loại sản phẩm để bắt đầu; phần ánh xạ thuộc tính và mọi thứ khác sửa ở trang của loại đó.',
    'create_product_type' => 'Tạo loại sản phẩm',
    'edit_title' => 'Sửa loại sản phẩm',
    'back_to_product_types' => 'Quay lại danh sách loại sản phẩm',
    'field_name_create_hint' => 'Mã định danh duy nhất sẽ được sinh từ tên.',

    'header_products' => '{count} sản phẩm',
    'header_attributes' => '{product} thuộc tính sản phẩm · {variant} thuộc tính biến thể',

    'column_product_type' => 'Tên',
    'column_description' => 'Mô tả',
    'column_attributes' => 'Thuộc tính',
    'column_products' => 'Sản phẩm',
    'column_status' => 'Trạng thái',
    'attributes_summary' => '{product} · {variant}',

    'search_placeholder' => 'Tìm loại sản phẩm',
    'filter_status' => 'Trạng thái',
    'filter_all_statuses' => 'Mọi trạng thái',
    'sort_recent' => 'Mới nhất trước',
    'sort_oldest' => 'Cũ nhất trước',
    'sort_name' => 'Tên A-Z',
    'sort_products' => 'Nhiều sản phẩm nhất',
    'count_of' => '{shown} trên {total}',
    'clear_filters' => 'Xoá bộ lọc',

    'empty_title' => 'Không có loại sản phẩm nào khớp',
    'empty_description' => 'Thử xoá từ khoá tìm kiếm hoặc bộ lọc trạng thái, hoặc tạo loại sản phẩm mới.',
    'empty_none_title' => 'Chưa có loại sản phẩm nào',
    'empty_none_description' => 'Tạo loại sản phẩm đầu tiên để quy định sản phẩm mang những gì.',

    'status_active' => 'Đang dùng',
    'status_draft' => 'Bản nháp',
    'status_active_help' => 'Xuất hiện trong luồng tạo sản phẩm.',
    'status_draft_help' => 'Ẩn khỏi luồng tạo sản phẩm.',
    'bulk_set_active' => 'Chuyển thành đang dùng',
    'bulk_set_draft' => 'Chuyển thành nháp',

    'section_basics' => 'Thông tin cơ bản',
    'section_basics_description' => 'Cách nhận ra loại này trong toàn bộ trang quản trị.',
    'field_name' => 'Tên',
    'field_handle' => 'Mã định danh',
    'field_handle_hint' => 'phải là duy nhất',
    'field_status' => 'Trạng thái',
    'field_description' => 'Mô tả',
    'field_description_help' => 'Ghi chú nội bộ cho đội của bạn — loại này gồm những gì và dùng vào việc gì.',
    'field_default_tax_class' => 'Nhóm thuế mặc định',
    'field_default_tax_class_hint' => 'Điền sẵn cho sản phẩm mới thuộc loại này.',
    'no_tax_class' => 'Không đặt mặc định',
    'section_about' => 'Về loại này',
    'attributes_description' => 'Nội dung cho các trường riêng của loại này — gán chúng vào loại sản phẩm trong Cài đặt.',

    'section_product_attributes' => 'Thuộc tính sản phẩm',
    'section_product_attributes_description' => 'Chọn thuộc tính nào áp cho sản phẩm thuộc loại này — chính là các trường nhân viên điền ở trang sản phẩm.',
    'section_variant_attributes' => 'Thuộc tính biến thể',
    'section_variant_attributes_description' => 'Thuộc tính thay đổi theo từng SKU — thường là những thứ như dung lượng hay màu sắc.',

    'side_status' => 'Trạng thái',
    'side_usage' => 'Mức sử dụng',
    'side_usage_products' => 'sản phẩm đang dùng loại này',
    'side_defaults' => 'Giá trị mặc định',
    'side_activity' => 'Nhật ký',
    'side_activity_empty' => 'Chưa có hoạt động nào.',
    'side_activity_see_all' => 'Xem tất cả',
    'last_updated' => 'Sửa lần cuối',

    'confirm_delete_title' => 'Xoá loại sản phẩm?',
    'confirm_delete' => 'Xoá loại sản phẩm này? Phần ánh xạ thuộc tính của nó mất theo.',
    'delete_product_type' => 'Xoá loại sản phẩm',

    'flash_created' => 'Đã tạo loại sản phẩm.',
    'flash_updated' => 'Đã cập nhật loại sản phẩm.',
    'flash_deleted' => 'Đã xoá loại sản phẩm.',
    'flash_delete_protected' => 'Loại sản phẩm này vẫn còn sản phẩm — chuyển chúng sang loại khác hoặc xoá đi trước khi xoá loại.',
    'flash_status_updated' => 'Đã cập nhật trạng thái loại sản phẩm.',
];
