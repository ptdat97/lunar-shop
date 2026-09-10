<?php

/* Việt hoá phần Giá. Xem orders.php cho cơ chế merge. */

return [

    'title' => 'Giá bán',
    'description' => 'Giá gốc, cộng thêm giá riêng theo nhóm khách hàng và giá theo bậc số lượng cho khách mua sỉ. Thêm giá so sánh để hiện giá niêm yết gạch ngang trên storefront.',
    'base_price' => 'Giá gốc',
    'base_price_per_currency' => '— mỗi loại tiền đang bật một dòng',
    'amount' => 'Giá',
    'compare_at' => 'Giá niêm yết',
    'compare_at_hint' => 'hiện dạng gạch ngang bên cạnh giá bán',
    'currency' => 'Tiền tệ',

    'group_title' => 'Giá theo nhóm khách hàng',
    'group_hint' => 'ghi đè giá gốc cho những nhóm khách hàng được chọn',
    'group_column' => 'Nhóm khách hàng',
    'group_empty' => 'Thêm mức giá riêng cho một nhóm khách hàng cụ thể — khách sỉ, nhân viên, khách VIP.',
    'group_add' => 'Thêm giá theo nhóm',

    'tier_title' => 'Giá theo bậc số lượng',
    'tier_hint' => 'mua từ số lượng tối thiểu trở lên thì được giá thấp hơn',
    'tier_empty' => 'Thưởng cho khách mua nhiều bằng giá thấp hơn khi đạt số lượng tối thiểu.',
    'tier_add' => 'Thêm bậc giá',
    'tier_min_qty' => 'SL tối thiểu',
    'tier_min_prefix' => '≥',
    'tier_any_customer' => 'Mọi khách hàng',

    'remove_row' => 'Xoá dòng giá',
    'flash_created' => 'Đã thêm giá.',
    'flash_updated' => 'Đã cập nhật giá.',
    'flash_deleted' => 'Đã xoá giá.',
];
