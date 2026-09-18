<?php

/**
 * Điểm thưởng. Mọi khoá ở đây đều **ra admin** (Cài đặt → Điểm thưởng) vì cả
 * sáu đều là quyết định kinh doanh, không phải cấu hình kỹ thuật — config chỉ
 * là giá trị mặc định khi chưa ai bấm gì.
 *
 * TẮT mặc định, cùng lý do với giới thiệu bạn: một chương trình điểm là một
 * khoản nợ với khách hàng: bật nó lên phải là một quyết định có người bấm nút.
 */
return [
    'enabled' => env('LOYALTY_ENABLED', false),

    // Chi bao nhiêu (đơn vị tiền LỚN) thì được 1 điểm.
    'earn_per_amount' => 10000,

    // 1 điểm đáng bao nhiêu tiền (đơn vị tiền LỚN) khi tiêu.
    // Mặc định 10.000₫ → 1 điểm → 200₫ = hoàn 2%.
    'point_value' => 200,

    // Chờ bao lâu sau khi trả tiền thì điểm mới tiêu được. Đây là hạn đổi/trả
    // của shop — cùng khái niệm mà thưởng giới thiệu bạn đang dùng.
    'hold_days' => 14,

    // Điểm sống bao lâu kể từ lúc khả dụng. 0 = không hết hạn.
    'expire_days' => 365,

    'min_redeem' => 10,

    // Trần phần trăm giá trị đơn được trả bằng điểm. KHÔNG phải để tiết kiệm:
    // đơn 0 đồng thì cổng thanh toán từ chối, và lỗi nổ ở bước cuối của khách.
    'max_percent' => 50,
];
