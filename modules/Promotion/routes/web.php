<?php

use Illuminate\Support\Facades\Route;
use Modules\Promotion\Http\Controllers\Storefront\PromotionPageController;
use Modules\Promotion\Http\Controllers\Storefront\ReferralLinkController;

// Storefront (Blade) routes for the Promotion module.
Route::middleware('storefront')->group(function (): void {
    // Link mời bạn bè. Nằm trong nhóm `storefront` vì mã được giữ trong SESSION
    // cho tới lúc khách đăng ký — API stateless không có session để giữ.
    //
    // Route riêng thay vì bắt `?ref=` trên mọi trang: một tham số query chỉ được
    // đọc ở trang nào có middleware đọc nó, và thêm middleware vào cả nhóm
    // storefront là đổi đường đi của mọi request để phục vụ một link.
    Route::get('r/{code}', ReferralLinkController::class)->name('storefront.referral.link');

    Route::get('promotions', [PromotionPageController::class, 'index'])
        ->name('storefront.promotions');
    Route::get('promotions/{handle}', [PromotionPageController::class, 'show'])
        ->name('storefront.promotion');
});
