<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route middleware
    |--------------------------------------------------------------------------
    |
    | Áp cho CẢ route đã đăng nhập lẫn route xác thực (login, thử thách hai lớp,
    | đặt lại mật khẩu) — xem PanelServiceProvider::registerRoutes.
    |
    | `lunar.panel` là nhóm mặc định của package, giữ nguyên. SkipPanelTwoFactor
    | là bổ sung của dự án: biến đăng nhập hai bước của panel thành chỉ-mật-khẩu
    | khi `staff.require_two_factor` tắt. Nó chỉ chạy SAU khi mật khẩu đã được
    | chấp nhận — xem chính class đó.
    |
    */
    'route_middleware' => [
        'lunar.panel',
        \Modules\Core\Http\Middleware\SkipPanelTwoFactor::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Enable Variants
    |--------------------------------------------------------------------------
    |
    | When `true` this will show the Variants manager when editing a product. If your
    | storefront doesn't support variants, set this to false.
    |
    */
    'enable_variants' => true,

    /*
    |--------------------------------------------------------------------------
    | PDF Streaming
    |--------------------------------------------------------------------------
    |
    | When handling PDF's in the panel, you can decide whether to stream the PDF in
    | a new tab or download the PDF to your hard drive.
    |
    | Available options are 'download' or 'stream'
    |
    */
    'pdf_rendering' => 'download',

    /*
    |--------------------------------------------------------------------------
    | Enable Scout when searching on supported models.
    |--------------------------------------------------------------------------
    |
    | Some models in the core have Scout implemented as a search driver, if you
    | want to use Scout when possible on tables in the panel, enable it here.
    |
    */
    'scout_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Navigation counts
    |--------------------------------------------------------------------------
    |
    | The admin panel will show a count of orders in the left navigation.
    | This is based upon specific order statuses. You can define the statuses
    | to include in the count below.
    |
    */
    'order_count_statuses' => ['payment-received'],

];
