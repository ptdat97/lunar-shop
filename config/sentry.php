<?php

use Modules\Core\Support\SentryScrubber;

/*
|--------------------------------------------------------------------------
| Sentry
|--------------------------------------------------------------------------
|
| Lỗi production trước đây chỉ nằm trong storage/logs: khách gặp 500 giữa lúc
| thanh toán thì KHÔNG AI BIẾT, trừ khi khách chịu khó nhắn tin (roadmap P0 §2).
|
| ⚠️ TẮT MẶC ĐỊNH. Không có DSN thì SDK không gửi gì cả, không mở kết nối nào —
| đặt SENTRY_LARAVEL_DSN mới bật. Điều đó là cố ý: bật một đường truyền dữ liệu
| ra bên thứ ba phải là một quyết định có người bấm nút, không phải hệ quả phụ
| của việc cài package.
|
*/

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Tên môi trường hiện trên Sentry. Không đặt thì mọi lỗi từ máy dev lẫn
    // production đổ chung một chỗ và không lọc ra được.
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Gắn phiên bản vào lỗi để biết bản deploy nào gây ra. Trên server dùng
    // `SENTRY_RELEASE=$(git rev-parse --short HEAD)` trong quy trình deploy.
    'release' => env('SENTRY_RELEASE'),

    /*
     * PHẢI là false. Bật lên là Sentry tự đính kèm IP, cookie và thân request
     * vào MỌI sự kiện — với một shop thì đó là địa chỉ giao hàng, email, số điện
     * thoại và tham số cổng thanh toán, gửi thẳng sang bên thứ ba.
     *
     * Cái ta cần để truy lỗi là ID nhân viên / khách hàng, và SentryScrubber
     * gắn đúng chừng đó.
     */
    'send_default_pii' => false,

    /*
     * Lưới lọc cuối trước khi một sự kiện rời khỏi máy chủ. `send_default_pii`
     * đã chặn phần lớn, nhưng dữ liệu nhạy cảm vẫn lọt vào theo đường khác:
     * chuỗi query trong URL, breadcrumb của câu SQL, biến trong stack trace.
     * Xem SentryScrubber để biết chính xác cái gì bị xoá.
     */
    'before_send' => [SentryScrubber::class, 'beforeSend'],
    'before_send_transaction' => [SentryScrubber::class, 'beforeSendTransaction'],

    /*
     * Không lấy mẫu performance mặc định (null = tắt). Free tier của Sentry
     * tính transaction, và một shop SME cần BÁO ĐỘNG LỖI chứ chưa cần hồ sơ
     * hiệu năng — bật lên là đốt hạn mức vào thứ chưa dùng tới.
     */
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null
        ? null
        : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    'breadcrumbs' => [
        'logs' => true,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => true,
        // Ràng buộc của câu SQL chứa nguyên giá trị: email, token, địa chỉ.
        // Biết câu truy vấn nào chạy là đủ để truy lỗi.
        'sql_bindings' => false,
        'queue_info' => true,
        'command_info' => true,
        'http_client_requests' => true,
        'notifications' => true,
    ],

    'tracing' => [
        'queue_job_transactions' => false,
        'sql_bindings' => false,
        'default_integrations' => true,
    ],
];
