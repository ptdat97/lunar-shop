<?php

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Staff;
use Modules\Core\Support\Settings;

/**
 * Refuse to call a deployment "done" while the shop is configured to lose money.
 *
 * Nothing in this project stopped a production deploy from pointing at the
 * payment sandbox, running with APP_DEBUG on, or shipping the demo staff
 * password. Each of those is silent: the site comes up, the pages render, and
 * the first symptom is a customer paying into a test gateway or a stack trace
 * with credentials in it on a public URL.
 *
 * This is a DEPLOY GATE, not a boot-time check. A boot-time throw would take
 * the whole shop down over, say, a missing mail credential — the cure being
 * worse than the disease. Run it in the pipeline after `migrate` and before
 * traffic is switched over; a non-zero exit means do not switch.
 *
 * Two severities:
 * - FAIL  → exit 1. Money, security, or "the shop cannot function".
 * - WARN  → exit 0, but printed. Worth fixing, not worth blocking a release.
 *
 * `--env-only` skips every check that needs the database, so the gate can run
 * before migrations on a fresh host.
 */
class Preflight extends Command
{
    protected $signature = 'shop:preflight
                            {--env-only : Skip checks that need the database}';

    protected $description = 'Kiểm tra cấu hình trước khi mở traffic vào production';

    /** Hosts that mean "this is a test gateway, no real money moves". */
    private const SANDBOX_HOSTS = [
        'sandbox.vnpayment.vn',
        'test-payment.momo.vn',
    ];

    /** @var list<array{level: string, check: string, detail: string}> */
    private array $results = [];

    public function handle(Settings $settings): int
    {
        $production = app()->environment('production');

        $this->line('Môi trường: <comment>'.app()->environment().'</comment>');
        $this->newLine();

        $this->checkApp($production);
        $this->checkPayments($settings, $production);
        $this->checkGatewayCurrency($settings, $production);
        $this->checkInfrastructure($production);

        if (! $this->option('env-only')) {
            $this->checkDatabase($production);
        }

        return $this->report();
    }

    private function checkApp(bool $production): void
    {
        $this->assert(
            filled(config('app.key')),
            'APP_KEY',
            'Chưa sinh khoá — session và mọi giá trị mã hoá sẽ hỏng. `php artisan key:generate`.',
        );

        $this->assert(
            ! $production || ! config('app.debug'),
            'APP_DEBUG',
            'Đang bật ở production: stack trace lộ ra public, kèm cả biến môi trường.',
        );

        $this->assert(
            ! $production || str_starts_with((string) config('app.url'), 'https://'),
            'APP_URL',
            'Không phải https ở production — link trong email và callback thanh toán sẽ sinh sai scheme.',
        );

        $this->warnIf(
            $production && config('app.env') !== 'production',
            'APP_ENV',
            'Ứng dụng nghĩ mình đang ở "'.config('app.env').'".',
        );
    }

    private function checkPayments(Settings $settings, bool $production): void
    {
        $gateways = [
            'VNPay' => [
                'credential' => 'payment.vnpay.tmn_code',
                'secret' => 'payment.vnpay.hash_secret',
                'urls' => ['payment.vnpay.payment_url', 'payment.vnpay.api_url'],
            ],
            'MoMo' => [
                'credential' => 'payment.momo.partner_code',
                'secret' => 'payment.momo.secret_key',
                'urls' => ['payment.momo.endpoint', 'payment.momo.refund_url'],
            ],
        ];

        foreach ($gateways as $name => $spec) {
            $configured = filled($settings->get($spec['credential']));

            if (! $configured) {
                // Not an error: a shop may deliberately run on COD only. The
                // gateway is simply not offered at checkout (CheckoutService).
                $this->note($name, 'Chưa cấu hình — không được chào ở checkout. Bỏ qua.');

                continue;
            }

            $this->assert(
                filled($settings->get($spec['secret'])),
                "{$name} secret",
                'Có mã merchant nhưng thiếu khoá ký. Mọi callback sẽ trượt xác thực chữ ký.',
            );

            foreach ($spec['urls'] as $key) {
                $url = (string) $settings->get($key);

                if ($url === '') {
                    continue;
                }

                $this->assert(
                    ! $production || ! $this->isSandbox($url),
                    "{$name} endpoint",
                    "`{$key}` vẫn trỏ sandbox ở production: {$url} — khách sẽ 'thanh toán' bằng tiền test.",
                );
            }
        }
    }

    /**
     * A gateway that can only settle one currency, on a shop priced in another.
     *
     * VNPay and MoMo settle VND and nothing else. If the shop's default currency
     * is something else, GatewayReconciler refuses every callback — correctly,
     * because comparing a VND figure against a total in another unit is
     * meaningless. But the shopper still gets sent to the gateway and still
     * pays; the money lands and the order never turns green.
     *
     * Better to say so before traffic than to discover it from a customer.
     */
    private function checkGatewayCurrency(Settings $settings, bool $production): void
    {
        $shopCurrency = (string) (Currency::getDefault()?->code ?? '');

        // Chưa có tiền tệ mặc định là một sự cố khác hẳn (shop chưa migrate/seed
        // xong), và báo nó ở đây dưới dạng "lệch tiền tệ cổng thanh toán" chỉ
        // làm người đọc đi sai hướng.
        if ($shopCurrency === '') {
            return;
        }

        $gateways = [
            'VNPay' => ['credential' => 'payment.vnpay.tmn_code', 'settles' => 'VND'],
            'MoMo' => ['credential' => 'payment.momo.partner_code', 'settles' => 'VND'],
        ];

        foreach ($gateways as $name => $spec) {
            if (blank($settings->get($spec['credential']))) {
                continue;
            }

            $this->assert(
                ! $production || $shopCurrency === $spec['settles'],
                "{$name} × tiền tệ",
                "Shop tính tiền bằng {$shopCurrency} nhưng {$name} chỉ settle {$spec['settles']}. "
                    .'Khách vẫn bị đẩy sang cổng và vẫn trả tiền, nhưng mọi callback sẽ bị từ chối '
                    .'và đơn không bao giờ chuyển sang đã thanh toán.',
            );
        }
    }

    private function checkInfrastructure(bool $production): void
    {
        $this->assert(
            ! $production || config('session.driver') !== 'file',
            'Session driver',
            'Đang dùng `file` ở production — không chia sẻ được giữa nhiều instance, giỏ hàng sẽ nhảy.',
        );

        $this->assert(
            ! $production || config('queue.default') !== 'sync',
            'Queue driver',
            'Đang `sync`: email và job ảnh chạy ngay trong request, khách chờ cả lượt gửi mail.',
        );

        $this->warnIf(
            $production && config('cache.default') === 'file',
            'Cache driver',
            'Đang `file`. Chạy được, nhưng cache section/setting không chia sẻ giữa các instance.',
        );

        $this->warnIf(
            $production && config('mail.default') === 'log',
            'Mail transport',
            'Đang ghi log thay vì gửi — khách không nhận được email xác nhận đơn nào.',
        );

        // Cảnh báo, không chặn: một shop chạy được mà không có error tracker,
        // nó chỉ chạy mù. Chặn phát hành vì thiếu nó là buộc người ta phải có
        // tài khoản Sentry mới deploy được, thứ không phải lúc nào cũng đúng.
        $this->warnIf(
            $production && blank(config('sentry.dsn')),
            'Theo dõi lỗi',
            'Chưa đặt SENTRY_LARAVEL_DSN — lỗi production chỉ nằm trong storage/logs, '
                .'khách gặp 500 lúc thanh toán thì không ai được báo.',
        );

        // send_default_pii bật ở production là gửi IP, cookie và thân request
        // của khách sang bên thứ ba. Cái này thì CHẶN.
        $this->assert(
            ! $production || ! config('sentry.send_default_pii'),
            'Sentry PII',
            'send_default_pii đang bật: IP, cookie và thân request của khách sẽ được gửi kèm mọi lỗi.',
        );

        // Cảnh báo chứ không chặn: report-only vẫn tốt hơn không có CSP, và
        // chặn phát hành vì nó sẽ khiến người ta đặt `off` cho xong việc.
        $this->warnIf(
            $production && config('security.csp.mode') !== 'enforce',
            'CSP',
            'Đang ở chế độ "'.config('security.csp.mode').'" — báo vi phạm nhưng không chặn gì. '
                .'Đọc vi phạm thật rồi đổi CSP_MODE=enforce.',
        );

        $this->assert(
            ! $production || config('security.csp.mode') !== 'off'
                || filled(config('security.headers.X-Content-Type-Options')),
            'Header bảo mật',
            'Tắt CSP mà cũng không còn header tĩnh nào — response không có lớp bảo vệ nào.',
        );
    }

    private function checkDatabase(bool $production): void
    {
        if (! Schema::hasTable('lunar_staff')) {
            $this->assert(false, 'Migration', 'Bảng `lunar_staff` chưa có — chạy `php artisan migrate` trước.');

            return;
        }

        $demoEmail = (string) env('DEMO_STAFF_EMAIL', 'admin@lunar-shop.test');

        $this->assert(
            ! $production || ! Staff::query()->where('email', $demoEmail)->exists(),
            'Tài khoản demo',
            "Tài khoản seeder `{$demoEmail}` còn tồn tại ở production — mật khẩu của nó nằm trong .env.example.",
        );
    }

    private function isSandbox(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        foreach (self::SANDBOX_HOSTS as $sandbox) {
            if ($host === $sandbox) {
                return true;
            }
        }

        return false;
    }

    private function assert(bool $passed, string $check, string $detail): void
    {
        $this->results[] = [
            'level' => $passed ? 'ok' : 'fail',
            'check' => $check,
            'detail' => $detail,
        ];
    }

    private function warnIf(bool $tripped, string $check, string $detail): void
    {
        $this->results[] = [
            'level' => $tripped ? 'warn' : 'ok',
            'check' => $check,
            'detail' => $detail,
        ];
    }

    private function note(string $check, string $detail): void
    {
        $this->results[] = ['level' => 'note', 'check' => $check, 'detail' => $detail];
    }

    private function report(): int
    {
        $failures = array_filter($this->results, fn ($r) => $r['level'] === 'fail');
        $warnings = array_filter($this->results, fn ($r) => $r['level'] === 'warn');

        foreach ($this->results as $result) {
            match ($result['level']) {
                'fail' => $this->line("  <fg=red>✗</> {$result['check']} — {$result['detail']}"),
                'warn' => $this->line("  <fg=yellow>!</> {$result['check']} — {$result['detail']}"),
                'note' => $this->line("  <fg=gray>·</> {$result['check']} — {$result['detail']}"),
                default => $this->line("  <fg=green>✓</> {$result['check']}"),
            };
        }

        $this->newLine();

        if ($failures !== []) {
            $this->error(count($failures).' hạng mục CHẶN phát hành. Đừng mở traffic.');

            return self::FAILURE;
        }

        if ($warnings !== []) {
            $this->warn(count($warnings).' cảnh báo — phát hành được, nhưng nên xử lý.');

            return self::SUCCESS;
        }

        $this->info('Preflight sạch.');

        return self::SUCCESS;
    }
}
