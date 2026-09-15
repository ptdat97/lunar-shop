<?php

namespace Modules\Promotion\Console;

use Illuminate\Console\Command;
use Modules\Promotion\Services\ReferralService;

/**
 * Phát thưởng giới thiệu cho những đơn đã qua thời gian đổi/trả.
 *
 * Hằng ngày chứ không phải mỗi mười phút: ngưỡng chờ tính bằng NGÀY, quét dày
 * hơn chỉ tốn truy vấn mà không phát thưởng sớm hơn được.
 *
 * Đây là nửa thứ hai của luật "thưởng sau khi hết hạn đổi/trả". Nửa thứ nhất là
 * listener `OrderPaid` — nó chỉ ĐÁNH DẤU lượt giới thiệu là đang chờ, không
 * phát gì. Nhờ tách hai nửa mà khách trả hàng xong vẫn không ăn thưởng: lượt đó
 * bị đóng lại thay vì được phát.
 *
 * Tự thoát sớm khi tính năng còn tắt, như các lệnh quét khác của shop.
 */
class ReleaseReferralRewards extends Command
{
    protected $signature = 'referrals:release
                            {--days= : Ghi đè số ngày chờ sau khi đơn được thanh toán}
                            {--limit=200 : Số lượt xét tối đa mỗi lần}
                            {--dry-run : Chỉ liệt kê, không phát coupon và không đổi trạng thái}';

    protected $description = 'Phát thưởng giới thiệu sau khi đơn của người được mời đã qua thời gian đổi/trả';

    public function handle(ReferralService $referrals): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $referrals->enabled() && ! $dryRun) {
            $this->line('Giới thiệu bạn đang TẮT (Cài đặt → Giới thiệu bạn). Bỏ qua.');

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null
            ? max(0, (int) $this->option('days'))
            : $referrals->rewardDelayDays();

        $due = $referrals->dueForReward($days, (int) $this->option('limit'));

        if ($due->isEmpty()) {
            $this->line("Không có lượt giới thiệu nào chờ quá {$days} ngày.");

            return self::SUCCESS;
        }

        $this->line("Tìm thấy {$due->count()} lượt đủ điều kiện xét thưởng.");

        if ($dryRun) {
            foreach ($due as $claim) {
                $this->line(sprintf(
                    '  [thử] #%d · %s · đơn %s',
                    $claim->id,
                    $claim->referrer?->first_name ?: 'n/a',
                    $claim->order?->reference ?: 'n/a',
                ));
            }

            $this->info('Chạy thử — không phát coupon và không đổi trạng thái nào.');

            return self::SUCCESS;
        }

        $released = 0;
        $voided = 0;

        foreach ($due as $claim) {
            match ($referrals->settle($claim)) {
                'released' => $released++,
                'voided' => $voided++,
                default => null,
            };
        }

        $this->info(
            "Đã phát {$released} coupon thưởng"
            .($voided ? ", đóng {$voided} lượt vì đơn đã trả lại hoặc hoàn tiền" : '')
            .'.'
        );

        return self::SUCCESS;
    }
}
