<?php

namespace Modules\Promotion\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;

/**
 * Điểm thưởng: tỉ lệ cộng, giá trị điểm, hạn chờ, hạn dùng, trần tiêu.
 *
 * Group riêng (`loyalty`), như `referral` và vì đúng lý do đó: `Settings::put()`
 * thay **cả** group, nên hai trang cài đặt dùng chung một group sẽ xoá khoá của
 * nhau ở mỗi lần lưu.
 */
class LoyaltySettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'loyalty';
    }

    public function label(): string
    {
        return __('admin.loyalty_settings.title');
    }

    /** `tag` chứ không phải `gift`: panel chỉ có đúng bộ icon trong Icon.vue. */
    public function icon(): string
    {
        return 'tag';
    }

    public function priority(): int
    {
        return 75;
    }

    public function description(): ?string
    {
        return __('admin.loyalty_settings.description');
    }

    public function fields(): array
    {
        return [
            Field::toggle('enabled', __('admin.loyalty_settings.enabled'))
                ->default(false)
                ->help(__('admin.loyalty_settings.enabled_help')),

            Field::number('earn_per_amount', __('admin.loyalty_settings.earn_per_amount'))
                ->rules('min:1')->default(10000)->width(4)
                ->help(__('admin.loyalty_settings.earn_per_amount_help')),

            Field::number('point_value', __('admin.loyalty_settings.point_value'))
                ->rules('min:0')->default(200)->width(4)
                ->help(__('admin.loyalty_settings.point_value_help')),

            Field::number('min_redeem', __('admin.loyalty_settings.min_redeem'))
                ->rules('min:1')->default(10)->width(4),

            Field::number('hold_days', __('admin.loyalty_settings.hold_days'))
                ->rules('min:0', 'max:120')->default(14)->width(4)
                ->help(__('admin.loyalty_settings.hold_days_help')),

            Field::number('expire_days', __('admin.loyalty_settings.expire_days'))
                ->rules('min:0', 'max:3650')->default(365)->width(4)
                ->help(__('admin.loyalty_settings.expire_days_help')),

            Field::number('max_percent', __('admin.loyalty_settings.max_percent'))
                ->rules('min:1', 'max:100')->default(50)->width(4)
                ->help(__('admin.loyalty_settings.max_percent_help')),
        ];
    }
}
