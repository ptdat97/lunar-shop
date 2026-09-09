<?php

/*
 * Chính sách đăng nhập cho nhân viên trên panel Lunar.
 *
 * Nằm ở config chứ không phải `app_settings`: một công tắc tắt xác thực hai lớp
 * mà lại sửa được từ chính panel là một đường leo thang đặc quyền — chiếm được
 * một phiên admin là tắt luôn được lớp bảo vệ cho mọi lần sau.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Bắt buộc lớp thứ hai khi đăng nhập panel
    |--------------------------------------------------------------------------
    |
    | Lunar 2.0 KHÔNG có đường đăng nhập chỉ-mật-khẩu: mọi lần đăng nhập đều là
    | thử thách hai bước — TOTP nếu nhân viên đã ghép app xác thực, không thì mã
    | sáu số gửi qua email (docs.lunarphp.com/2.x/admin/access-control).
    |
    | Shop này là một cửa hàng đơn, một tài khoản nhân viên, và mỗi lần vào admin
    | phải chờ một email làm việc vận hành hằng ngày nặng nề hơn mức đáng. Đặt
    | `false` để bỏ bước hai: mật khẩu đúng là vào thẳng.
    |
    | Cái KHÔNG mất đi khi tắt: xác thực mật khẩu, giới hạn số lần thử ở bước
    | mật khẩu, phân quyền, và màn hình Bảo mật trong tài khoản. Bật lại chỉ cần
    | đổi biến môi trường — TOTP đã ghép vẫn còn nguyên và có hiệu lực trở lại.
    |
    | Cái MẤT đi: mật khẩu bị lộ là vào được panel. Nếu shop có nhiều nhân viên,
    | hoặc panel mở ra Internet công cộng, hãy để `true`.
    |
    */
    'require_two_factor' => (bool) env('PANEL_REQUIRE_TWO_FACTOR', false),
];
