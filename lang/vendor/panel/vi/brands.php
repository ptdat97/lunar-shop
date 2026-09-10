<?php

/* Việt hoá màn hình Thương hiệu. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Thương hiệu',
    'description' => 'Quản lý các nhà sản xuất và nhãn hàng mà sản phẩm thuộc về. Mỗi thương hiệu gom logo, câu chuyện thương hiệu, trường tuỳ biến và những bộ sưu tập nó góp mặt.',
    'new_brand' => 'Thêm thương hiệu',
    'create_title' => 'Thêm thương hiệu',
    'create_description' => 'Đặt tên thương hiệu để bắt đầu; mọi thứ còn lại sửa ở trang thương hiệu.',
    'create_brand' => 'Tạo thương hiệu',
    'edit_title' => 'Sửa thương hiệu',
    'back_to_brands' => 'Quay lại danh sách thương hiệu',
    'delete_brand' => 'Xoá thương hiệu',
    'field_name_create_hint' => 'Mã định danh duy nhất và slug URL mặc định sẽ được sinh từ tên.',

    'header_collections' => '{count} bộ sưu tập',
    'header_products' => '{count} sản phẩm',
    'confirm_delete_brand_title' => 'Xoá thương hiệu?',

    'column_brand' => 'Tên',
    'column_description' => 'Mô tả',
    'column_collections' => 'Bộ sưu tập',
    'column_products' => 'Sản phẩm',
    'column_status' => 'Trạng thái',

    'search_placeholder' => 'Tìm thương hiệu',
    'filter_status' => 'Trạng thái',
    'filter_all_statuses' => 'Mọi trạng thái',
    'sort_recent' => 'Mới nhất trước',
    'sort_oldest' => 'Cũ nhất trước',
    'sort_name' => 'Tên A-Z',
    'sort_products' => 'Nhiều sản phẩm nhất',
    'count_of' => '{shown} trên {total}',
    'clear_filters' => 'Xoá bộ lọc',

    'empty_title' => 'Không có thương hiệu nào khớp',
    'empty_description' => 'Thử xoá từ khoá tìm kiếm hoặc bộ lọc trạng thái, hoặc tạo thương hiệu mới.',
    'empty_none_title' => 'Chưa có thương hiệu nào',
    'empty_none_description' => 'Tạo thương hiệu đầu tiên để bắt đầu sắp xếp danh mục.',

    'status_active' => 'Đang dùng',
    'status_draft' => 'Bản nháp',
    'status_active_help' => 'Nhân viên phụ trách hàng hoá thấy được, và hiện trên storefront khi đã đăng.',
    'status_draft_help' => 'Ẩn khỏi storefront và khỏi luồng tạo sản phẩm mới.',
    'bulk_set_active' => 'Chuyển thành đang dùng',
    'bulk_set_draft' => 'Chuyển thành nháp',

    'section_basics' => 'Thông tin cơ bản',
    'section_basics_description' => 'Cách nhận ra thương hiệu này trong trang quản trị và trên storefront.',
    'field_name' => 'Tên',
    'field_handle' => 'Mã định danh',
    'field_handle_hint' => 'phải là duy nhất',
    'field_status' => 'Trạng thái',
    'field_short_description' => 'Mô tả ngắn',
    'field_short_description_hint' => 'hiện ở dòng danh sách, và dùng làm nội dung dự phòng trên storefront',
    'field_description' => 'Mô tả',
    'attributes_description' => 'Nội dung biên tập cho trang storefront của thương hiệu — các trường như nút kêu gọi, cờ nổi bật hay câu chuyện thương hiệu.',

    'side_status' => 'Trạng thái',
    'side_collections' => 'Bộ sưu tập',
    'side_collections_hint' => 'Các nhóm sản phẩm được chọn lọc có thương hiệu này. Lưu cùng biểu mẫu.',
    'side_usage' => 'Mức sử dụng',
    'side_usage_products' => 'sản phẩm mang thương hiệu này',
    'side_usage_collections' => 'bộ sưu tập có góp mặt',
    'side_activity' => 'Nhật ký',
    'side_activity_empty' => 'Chưa có hoạt động nào.',
    'side_activity_see_all' => 'Xem tất cả',
    'last_updated' => 'Sửa lần cuối',

    'confirm_delete_brand' => 'Xoá thương hiệu này? Sản phẩm vẫn giữ nguyên dữ liệu nhưng mất liên kết thương hiệu.',
    'flash_created' => 'Đã tạo thương hiệu.',
    'flash_updated' => 'Đã cập nhật thương hiệu.',
    'flash_deleted' => 'Đã xoá thương hiệu.',
    'flash_delete_protected' => 'Thương hiệu này vẫn còn sản phẩm — chuyển chúng sang thương hiệu khác hoặc xoá đi trước.',
    'flash_status_updated' => 'Đã cập nhật trạng thái thương hiệu.',
];
