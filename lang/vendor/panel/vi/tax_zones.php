<?php

/* Việt hoá màn hình Khu vực thuế. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Khu vực thuế',
    'description' => 'Các vùng địa lý quyết định áp mức thuế nào.',
    'create_tax_zone' => 'Tạo khu vực thuế',
    'create_description' => 'Thêm một khu vực; đặt phạm vi và các mức thuế của nó ở màn hình chỉnh sửa.',
    'default_badge' => 'Mặc định',
    'empty_title' => 'Chưa có khu vực thuế nào',

    'column_name' => 'Tên',
    'column_type' => 'Kiểu',
    'column_rates' => 'Mức thuế',
    'column_status' => 'Trạng thái',

    'field_name' => 'Tên',
    'name_placeholder' => 'ví dụ: Việt Nam',
    'field_type' => 'Kiểu',
    'type_country' => 'Theo quốc gia',
    'type_state' => 'Theo tỉnh/thành',
    'type_postcode' => 'Theo mã bưu chính',
    'active_hint' => 'Khu vực đang tắt sẽ bị bỏ qua khi tính thuế.',
    'default_zone' => 'Khu vực mặc định',
    'default_zone_hint' => 'Dùng khi không khu vực nào khác khớp.',
    'default_locked_hint' => 'Đây là khu vực mặc định. Muốn đổi thì đặt một khu vực khác làm mặc định.',
    'default_unset_blocked' => 'Không bỏ được khu vực thuế mặc định. Hãy đặt một khu vực khác làm mặc định thay thế.',

    'section_details' => 'Chi tiết',
    'section_countries' => 'Quốc gia ({count})',
    'countries_desc' => 'Đơn giao tới những quốc gia này thuộc khu vực này.',
    'add_country_placeholder' => 'Thêm quốc gia…',
    'remove_country' => 'Xoá quốc gia',
    'no_countries' => 'Chưa có quốc gia nào.',

    'section_states' => 'Tỉnh/thành ({count})',
    'states_desc' => 'Đơn giao tới những tỉnh/thành này thuộc khu vực này.',
    'add_state_placeholder' => 'Thêm tỉnh/thành…',
    'remove_state' => 'Xoá tỉnh/thành',
    'no_states' => 'Chưa có tỉnh/thành nào.',

    'section_postcodes' => 'Mã bưu chính ({count})',
    'postcodes_desc' => 'Đơn giao tới những mã bưu chính này thuộc khu vực này. Dùng * làm ký tự đại diện.',
    'postcode_placeholder' => 'ví dụ: 70000*',
    'add_postcode' => 'Thêm',
    'remove_postcode' => 'Xoá mã bưu chính',
    'no_postcodes' => 'Chưa có mã bưu chính nào.',

    'section_customer_groups' => 'Nhóm khách hàng ({count})',
    'customer_groups_desc' => 'Giới hạn khu vực này cho một số nhóm khách hàng nhất định.',
    'add_customer_group_placeholder' => 'Thêm nhóm khách hàng…',
    'remove_customer_group' => 'Xoá nhóm khách hàng',
    'all_customer_groups' => 'Áp cho mọi nhóm khách hàng.',

    'section_rates' => 'Mức thuế ({count})',
    'rates_desc' => 'Mức có độ ưu tiên cao hơn sẽ thắng. Mỗi mức mang một tỷ lệ phần trăm cho từng nhóm thuế.',
    'add_rate' => 'Thêm mức thuế',
    'remove_rate' => 'Xoá mức thuế',
    'no_rates' => 'Chưa có mức thuế nào. Thêm một mức để bắt đầu thu thuế ở khu vực này.',
    'new_rate_name' => 'Mức thuế mới',
    'column_rate_name' => 'Tên mức thuế',
    'column_priority' => 'Độ ưu tiên',

    'edit_title' => 'Sửa khu vực thuế — {name}',
    'confirm_delete_tax_zone' => 'Bạn chắc chắn muốn xoá khu vực thuế này?',
    'confirm_delete_title' => 'Xoá khu vực thuế?',
    'confirm_delete_body' => '"{name}" sẽ bị xoá vĩnh viễn cùng phạm vi và các mức thuế của nó.',
    'delete_blocked_default' => 'Không xoá được khu vực thuế mặc định. Hãy đặt một khu vực khác làm mặc định trước.',

    'flash_created' => 'Đã tạo khu vực thuế.',
    'flash_updated' => 'Đã cập nhật khu vực thuế.',
    'flash_deleted' => 'Đã xoá khu vực thuế.',
];
