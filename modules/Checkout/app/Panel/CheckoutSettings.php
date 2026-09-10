<?php

namespace Modules\Checkout\Panel;

use Modules\Checkout\Services\AbandonedCartService;
use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;

/** Cách shop đối xử với giỏ hàng bị bỏ quên. */
class CheckoutSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        // Phải khớp tiền tố mà AbandonedCartService đọc (`checkout.*`). Lệch
        // một chữ là trang cài đặt lưu vào chỗ không ai đọc — đúng lớp lỗi đã
        // gặp nhiều lần trong dự án này.
        return 'checkout';
    }

    public function label(): string
    {
        return __('admin.checkout_settings.title');
    }

    public function icon(): string
    {
        return 'cart';
    }

    public function priority(): int
    {
        return 25;
    }

    public function fields(): array
    {
        return [
            // TẮT mặc định. Bật lên là lượt quét đầu tiên sẽ mail cho MỌI giỏ
            // đang nằm trong bảng, kể cả giỏ có từ trước khi có tính năng này.
            // Chạy `carts:remind-abandoned --dry-run` xem trước rồi hãy bật.
            Field::toggle('abandoned_cart_enabled', __('admin.checkout_settings.abandoned_cart_enabled'))
                ->help(__('admin.checkout_settings.abandoned_cart_enabled_help'))
                ->default(false),

            // Cận trên/dưới lấy từ chính hằng số của service: service tự kẹp giá
            // trị nó đọc, nên một form nhận 5 phút sẽ báo "đã lưu" trong khi hệ
            // thống lặng lẽ dùng 15.
            Field::number('abandoned_cart_minutes', __('admin.checkout_settings.abandoned_cart_minutes'))
                ->help(__('admin.checkout_settings.abandoned_cart_minutes_help'))
                ->rules('min:'.AbandonedCartService::MIN_DELAY_MINUTES, 'max:'.AbandonedCartService::MAX_DELAY_MINUTES)
                ->default(AbandonedCartService::DEFAULT_DELAY_MINUTES)
                ->width(6),
        ];
    }
}
