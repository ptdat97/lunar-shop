<?php

namespace Modules\Checkout\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Lunar\Core\Models\Cart;
use Modules\Checkout\Mail\AbandonedCartMail;
use Modules\Checkout\Services\AbandonedCartService;
use Modules\Theme\Services\LocaleService;
use Throwable;

/**
 * One "you left something behind" email per abandoned cart.
 *
 * Deliberately OFF until the shop turns it on (Admin → Cài đặt → Thanh toán).
 * A recovery sweep that starts emailing the moment it deploys would mail every
 * cart already sitting in the table — including carts from before the feature
 * existed, whose owners have long since moved on.
 *
 * Failure is contained per cart: one bad address must not stop the rest of the
 * sweep, and the cart is marked reminded either way so a broken mail transport
 * cannot turn one nudge into ten once it recovers.
 */
class RemindAbandonedCarts extends Command
{
    protected $signature = 'carts:remind-abandoned
                            {--minutes= : Ghi đè thời gian chờ trước khi nhắc}
                            {--limit=100 : Số giỏ tối đa mỗi lượt quét}
                            {--dry-run : Chỉ liệt kê, không gửi và không đánh dấu}';

    protected $description = 'Gửi một email nhắc cho mỗi giỏ hàng bị bỏ quên';

    public function handle(AbandonedCartService $carts, LocaleService $locales): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // The dry run has to work even when the feature is off — that is how a
        // shop looks at what it WOULD send before switching it on.
        if (! $carts->enabled() && ! $dryRun) {
            $this->line('Nhắc giỏ bỏ quên đang TẮT (Cài đặt → Thanh toán). Bỏ qua.');

            return self::SUCCESS;
        }

        $minutes = $this->option('minutes') !== null
            ? max(AbandonedCartService::MIN_DELAY_MINUTES, (int) $this->option('minutes'))
            : $carts->delayMinutes();

        $due = $carts->dueForReminder($minutes, (int) $this->option('limit'));

        if ($due->isEmpty()) {
            $this->line("Không có giỏ nào bỏ quên quá {$minutes} phút.");

            return self::SUCCESS;
        }

        $this->line("Tìm thấy {$due->count()} giỏ bỏ quên quá {$minutes} phút.");

        $sent = 0;
        $failed = 0;

        foreach ($due as $cart) {
            $email = $carts->recipient($cart);

            if ($dryRun) {
                $this->line(sprintf(
                    '  [thử] giỏ #%d · %d dòng · %s · sửa lần cuối %s',
                    $cart->id,
                    $cart->lines->count(),
                    $email,
                    $cart->updated_at?->diffForHumans(),
                ));

                continue;
            }

            // Marked BEFORE sending, not after: if the process dies between the
            // send and the write, an unmarked cart gets nudged again on the next
            // sweep. Losing one reminder is better than sending two.
            // Giỏ web không có sẵn public_token (Lunar chỉ mint cho giỏ
            // stateless), mà link khôi phục thì cần. Mint trước khi dựng mail.
            $carts->ensureRecoveryToken($cart);

            $carts->markReminded($cart);

            try {
                Mail::to($email)
                    ->locale($this->localeFor($cart, $locales))
                    ->send(new AbandonedCartMail($cart));

                $sent++;
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  giỏ #{$cart->id}: {$e->getMessage()}");
            }
        }

        if ($dryRun) {
            $this->info('Chạy thử — không gửi gì và không đánh dấu gì.');

            return self::SUCCESS;
        }

        $this->info("Đã gửi {$sent} email".($failed ? ", {$failed} lỗi" : '').'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The shopper's language, not the scheduler's.
     *
     * This runs from cron, where the active locale is the app default — so
     * unlike a request-time mail, there is no ambient locale worth trusting.
     */
    private function localeFor(Cart $cart, LocaleService $locales): string
    {
        $preferred = $cart->meta['locale'] ?? null;

        return is_string($preferred) && $locales->isSupported($preferred)
            ? $preferred
            : $locales->default();
    }
}
