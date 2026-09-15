<?php

namespace Modules\Promotion\Panel;

use Modules\Core\Panel\Field;
use Modules\Core\Panel\StoredSettingsGroup;
use Modules\Promotion\Services\ReferralService;

/**
 * Giới thiệu bạn: bật/tắt, giảm giá cho người được mời, thưởng cho người mời.
 *
 * Group riêng (`referral`) chứ không dùng chung `promotion` với hạng thành viên:
 * `Settings::put()` thay **cả** group, nên hai trang cài đặt nằm chung một group
 * sẽ xoá khoá của nhau ở mỗi lần lưu.
 *
 * Cả sáu khoá đều là số hoặc cờ — không có khoá nào là bí mật, nên không cần
 * nhánh "để trống nghĩa là giữ nguyên" như các trang có API key.
 */
class ReferralSettings extends StoredSettingsGroup
{
    public function key(): string
    {
        return 'referral';
    }

    public function label(): string
    {
        return __('admin.referral_settings.title');
    }

    public function icon(): string
    {
        return 'percent';
    }

    public function priority(): int
    {
        return 70;
    }

    public function description(): ?string
    {
        return __('admin.referral_settings.description');
    }

    public function fields(): array
    {
        return [
            Field::toggle('enabled', __('admin.referral_settings.enabled'))
                ->default(false),

            Field::number('welcome_percentage', __('admin.referral_settings.welcome_percentage'))
                ->rules('min:0', 'max:100')->default(10)->width(3)
                ->help(__('admin.referral_settings.welcome_percentage_help')),

            Field::number('welcome_valid_days', __('admin.referral_settings.welcome_valid_days'))
                ->rules('min:1', 'max:365')->default(30)->width(3),

            Field::number('reward_percentage', __('admin.referral_settings.reward_percentage'))
                ->rules('min:0', 'max:100')->default(10)->width(3),

            Field::number('reward_valid_days', __('admin.referral_settings.reward_valid_days'))
                ->rules('min:1', 'max:365')->default(60)->width(3),

            // Số ngày chờ chính là hạn đổi/trả của shop: một con số, hai nghĩa,
            // nên nhãn phải nói rõ cả hai.
            Field::number('reward_delay_days', __('admin.referral_settings.reward_delay_days'))
                ->rules('min:0', 'max:'.ReferralService::MAX_REWARD_DELAY_DAYS)
                ->default(ReferralService::DEFAULT_REWARD_DELAY_DAYS)->width(3)
                ->help(__('admin.referral_settings.reward_delay_days_help')),
        ];
    }
}
