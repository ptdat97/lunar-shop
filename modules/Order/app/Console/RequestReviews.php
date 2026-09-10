<?php

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Modules\Order\Mail\ReviewRequestMail;
use Modules\Order\Services\OrderMailer;
use Modules\Order\Services\ReviewRequestService;
use Throwable;

/**
 * One "how did it work out?" email per delivered order.
 *
 * Daily rather than every ten minutes: the delay is measured in days, so a
 * tighter schedule only costs queries without asking anyone sooner.
 *
 * Off until the shop turns it on, for the same reason as the abandoned-cart
 * sweep — the first run would otherwise email every customer the shop has ever
 * had, about orders they received months ago.
 */
class RequestReviews extends Command
{
    protected $signature = 'orders:request-reviews
                            {--days= : Ghi đè số ngày chờ sau khi giao}
                            {--limit=100 : Số đơn tối đa mỗi lượt}
                            {--dry-run : Chỉ liệt kê, không gửi và không đánh dấu}';

    protected $description = 'Xin đánh giá cho những đơn đã giao';

    public function handle(ReviewRequestService $requests, OrderMailer $mailer): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $requests->enabled() && ! $dryRun) {
            $this->line('Xin đánh giá sau mua đang TẮT (Cài đặt → Thanh toán & giỏ hàng). Bỏ qua.');

            return self::SUCCESS;
        }

        $days = $this->option('days') !== null
            ? max(ReviewRequestService::MIN_DELAY_DAYS, (int) $this->option('days'))
            : $requests->delayDays();

        $due = $requests->dueForRequest($days, (int) $this->option('limit'));

        if ($due->isEmpty()) {
            $this->line("Không có đơn nào đã giao quá {$days} ngày mà chưa xin đánh giá.");

            return self::SUCCESS;
        }

        $this->line("Tìm thấy {$due->count()} đơn đã giao quá {$days} ngày.");

        $sent = 0;
        $failed = 0;

        foreach ($due as $order) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [thử] đơn %s · %d dòng · %s',
                    $order->reference,
                    $order->lines->count(),
                    $mailer->recipient($order),
                ));

                continue;
            }

            // Marked first: losing one request beats sending two.
            $requests->markRequested($order);

            try {
                // Through OrderMailer so the recipient rule and the customer's
                // locale are the same ones every other order email uses — a
                // review request that arrives in the wrong language, or at an
                // address no other order email uses, is a bug nobody reports.
                $mailer->send($order, new ReviewRequestMail($order));
                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  đơn {$order->reference}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info('Chạy thử — không gửi gì và không đánh dấu gì.');

            return self::SUCCESS;
        }

        $this->info("Đã gửi {$sent} email".($failed ? ", {$failed} lỗi" : '').'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
