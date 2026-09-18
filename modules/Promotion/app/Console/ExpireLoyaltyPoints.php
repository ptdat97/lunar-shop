<?php

namespace Modules\Promotion\Console;

use Illuminate\Console\Command;
use Modules\Promotion\Services\LoyaltyService;

/**
 * Đóng các lô điểm đã quá hạn.
 *
 * Hết hạn phải là một BÚT TOÁN, không phải một điều kiện trong câu truy vấn số
 * dư. Nếu số dư tự lọc bỏ lô quá hạn thì sổ không còn cộng lại thành số dư
 * được, và câu hỏi "tháng trước khách mất bao nhiêu điểm" không ai trả lời nổi.
 */
class ExpireLoyaltyPoints extends Command
{
    protected $signature = 'loyalty:expire {--dry-run : Chỉ đếm, không ghi bút toán nào}';

    protected $description = 'Đóng phần chưa tiêu của các lô điểm đã hết hạn';

    public function handle(LoyaltyService $loyalty): int
    {
        if (! $loyalty->enabled()) {
            $this->info('Điểm thưởng đang tắt — không làm gì.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('--dry-run: không ghi bút toán nào.');

            return self::SUCCESS;
        }

        $closed = $loyalty->expireLots();

        $this->info($closed > 0
            ? "Đã đóng {$closed} điểm hết hạn."
            : 'Không có lô nào hết hạn.');

        return self::SUCCESS;
    }
}
