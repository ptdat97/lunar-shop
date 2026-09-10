<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | `mode`:
    |   enforce → trình duyệt CHẶN thứ vi phạm
    |   report  → chỉ báo vào console / report_uri, không chặn (mặc định)
    |   off     → không gửi header
    |
    | Mặc định `report` là cố ý. Một CSP sai không hỏng nửa vời — nó làm chết
    | JS hoặc chết ảnh trên toàn site, và triệu chứng chỉ hiện ở trình duyệt
    | khách chứ không có dòng log nào phía server. Chạy `report` vài ngày, đọc
    | vi phạm thật, rồi mới bật `enforce`.
    |
    | Storefront ĐỦ ĐIỀU KIỆN để enforce ngay: không có một inline script thực
    | thi nào (mọi <script> đều có src=, hoặc là application/json — dữ liệu,
    | không chạy), nguồn ngoài duy nhất là Google Fonts.
    |
    | Panel thì KHÔNG: nó là bundle Inertia/Vue của Lunar, ta không kiểm soát
    | nội dung, nên panel luôn chạy report-only bất kể `mode` (xem
    | SecurityHeaders::class).
    |
    */
    'csp' => [
        'mode' => env('CSP_MODE', 'report'),

        // Nơi trình duyệt POST báo cáo vi phạm. Bỏ trống thì vi phạm chỉ hiện
        // trong console DevTools — đủ để soi tay, không đủ để biết khách gặp gì.
        'report_uri' => env('CSP_REPORT_URI'),

        'directives' => [
            'default-src' => ["'self'"],

            // Giá trị lớn nhất của cả policy này: KHÔNG có 'unsafe-inline' và
            // KHÔNG có 'unsafe-eval'. Giữ được vì theme không có inline script.
            // Thêm ngoại lệ vào đây là gần như bỏ hết tác dụng chống XSS.
            'script-src' => ["'self'"],

            // 'unsafe-inline' ở đây là bắt buộc và chấp nhận được: theme có 11
            // thuộc tính style động (vị trí hotspot lookbook, ảnh nền slider).
            // Inline STYLE không thực thi mã, rủi ro thấp hơn hẳn inline script.
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'],

            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'"],

            // Chống clickjacking, mạnh hơn X-Frame-Options và là bản thay thế
            // hiện đại của nó (vẫn gửi cả hai cho trình duyệt cũ).
            'frame-ancestors' => ["'none'"],

            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],

            // Redirect sang VNPay/MoMo là GET (Location header), không phải form
            // POST — nên 'self' không chặn thanh toán. Kiểm lại nếu đổi cổng.
            'form-action' => ["'self'"],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HSTS
    |--------------------------------------------------------------------------
    |
    | CHỈ gửi ở production trên https. Gửi nhầm ở môi trường http sẽ khoá trình
    | duyệt vào https cho cả domain đó trong `max_age` giây — không rút lại được
    | từ phía server, khách phải tự xoá state trong trình duyệt.
    |
    | `preload` để mặc định TẮT: nộp domain vào danh sách preload của Chrome là
    | thao tác gần như một chiều, đừng bật cho tới khi https đã chạy ổn định.
    |
    */
    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('HSTS_PRELOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Header tĩnh
    |--------------------------------------------------------------------------
    |
    | Permissions-Policy tắt hẳn những API shop không dùng. Trình duyệt sẽ từ
    | chối chúng kể cả khi có script lạ chen được vào trang.
    |
    */
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
    ],
];
