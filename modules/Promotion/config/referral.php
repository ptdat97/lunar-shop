<?php

/*
 |--------------------------------------------------------------------------
 | Giới thiệu bạn (referral)
 |--------------------------------------------------------------------------
 |
 | Một khách có một mã riêng; bạn bè đăng ký qua mã đó nhận giảm giá cho đơn
 | đầu tiên, còn người mời nhận thưởng — nhưng chỉ SAU KHI đơn của người được
 | mời đã thanh toán và đã qua thời gian đổi/trả.
 |
 | Mọi khoá ở đây nằm PHẲNG trong group `referral` của `app_settings`:
 | `Settings::get('referral.enabled')` đọc group `referral`, khoá `enabled`, và
 | rơi về chính file này khi admin chưa lưu gì. Group chỉ một cấp — đừng lồng.
 |
 | Mặc định TẮT, cùng lý do với cứu giỏ hàng bỏ quên và xin đánh giá: bật một
 | kênh chi tiền (phát coupon) phải là quyết định có người bấm nút, không phải
 | hệ quả phụ của việc deploy.
 */

return [

    'enabled' => false,

    /*
     | Giảm giá cho NGƯỜI ĐƯỢC MỜI (%) — phát hành lúc họ đăng ký, dùng cho đơn
     | đầu tiên. Mã là coupon Lunar thật (một lần dùng, có hạn), nên nó đi qua
     | đúng đường mà mọi mã giảm giá khác trong shop đi.
     */
    'welcome_percentage' => 10,
    'welcome_valid_days' => 30,

    /*
     | Thưởng cho NGƯỜI MỜI (%) — phát hành khi đơn của người được mời đã qua
     | thời gian đổi/trả, không phải lúc đơn vừa được trả tiền.
     */
    'reward_percentage' => 10,
    'reward_valid_days' => 60,

    /*
     | Số ngày chờ sau khi đơn được thanh toán trước khi phát thưởng. Đây là
     | "hết hạn đổi/trả" của shop: phát thưởng sớm hơn thì khách trả hàng xong
     | vẫn ăn thưởng, và lệnh `referrals:release` không có gì để thu lại.
     */
    'reward_delay_days' => 14,

];
