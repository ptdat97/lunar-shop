<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Lunar\Core\Models\Currency;
use Tests\TestCase;

/**
 * The deploy gate has to actually stop a bad deploy.
 *
 * A check that only ever passes is worse than no check: it makes the pipeline
 * green and the team confident. Each case here configures the exact mistake it
 * is meant to catch and asserts a non-zero exit — the thing a CI step reads.
 */
class PreflightTest extends TestCase
{
    /**
     * Give the shop a default currency.
     *
     * PreflightTest cố ý không seed dữ liệu storefront, nên KHÔNG có dòng
     * currency nào — `update()` ở đây sẽ tác động 0 dòng và phép kiểm tiền tệ
     * bị bỏ qua, khiến test xanh vì lý do sai.
     */
    private function shopCurrency(string $code, int $decimals): void
    {
        Currency::query()->delete();

        Currency::create([
            'code' => $code,
            'name' => $code,
            'exchange_rate' => 1,
            'decimal_places' => $decimals,
            'default' => true,
            'enabled' => true,
        ]);
    }

    /** Pretend to be production without touching the real environment. */
    private function asProduction(array $config = []): int
    {
        app()->detectEnvironment(fn () => 'production');

        config(array_merge([
            'app.debug' => false,
            'app.url' => 'https://shop.example',
            'app.env' => 'production',
            'session.driver' => 'redis',
            'queue.default' => 'redis',
            'cache.default' => 'redis',
            'mail.default' => 'smtp',
        ], $config));

        return Artisan::call('shop:preflight', ['--env-only' => true]);
    }

    public function test_a_correct_production_config_passes(): void
    {
        $this->assertSame(0, $this->asProduction());
    }

    public function test_debug_mode_blocks_the_release(): void
    {
        $this->assertSame(1, $this->asProduction(['app.debug' => true]));
        $this->assertStringContainsString('APP_DEBUG', Artisan::output());
    }

    public function test_a_sandbox_payment_endpoint_blocks_the_release(): void
    {
        $exit = $this->asProduction([
            'payment.vnpay.tmn_code' => 'LIVECODE',
            'payment.vnpay.hash_secret' => 'live-secret',
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        ]);

        $this->assertSame(1, $exit, 'URL sandbox ở production phải chặn phát hành.');
        $this->assertStringContainsString('sandbox', Artisan::output());
    }

    public function test_a_live_endpoint_passes(): void
    {
        // Một cấu hình cổng "đã lên production" hoàn chỉnh phải gồm cả tiền tệ
        // settle được — VNPay chỉ nhận VND.
        $this->shopCurrency('VND', 0);

        $exit = $this->asProduction([
            'payment.vnpay.tmn_code' => 'LIVECODE',
            'payment.vnpay.hash_secret' => 'live-secret',
            'payment.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html',
            'payment.vnpay.api_url' => 'https://merchant.vnpay.vn/merchant_webapi/api/transaction',
        ]);

        $this->assertSame(0, $exit);
    }

    public function test_credentials_without_a_signing_key_block_the_release(): void
    {
        $exit = $this->asProduction([
            'payment.momo.partner_code' => 'LIVEPARTNER',
            'payment.momo.secret_key' => '',
            'payment.momo.endpoint' => 'https://payment.momo.vn/v2/gateway/api/create',
        ]);

        $this->assertSame(1, $exit, 'Có mã merchant mà thiếu khoá ký thì mọi callback trượt chữ ký.');
    }

    public function test_a_sync_queue_blocks_the_release(): void
    {
        $this->assertSame(1, $this->asProduction(['queue.default' => 'sync']));
    }

    /**
     * Sending customer IPs, cookies and request bodies to a third party is a
     * different order of mistake from a missing DSN — one is a gap, the other
     * is a leak. Only the leak blocks.
     */
    public function test_sentry_pii_blocks_the_release(): void
    {
        $exit = $this->asProduction([
            'sentry.dsn' => 'https://public@example.ingest.sentry.io/1',
            'sentry.send_default_pii' => true,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('PII', Artisan::output());
    }

    /**
     * A gateway that cannot settle the shop's currency takes the customer's
     * money and leaves the order unpaid — the reconciler refuses the callback,
     * correctly, but only after the shopper has already paid.
     */
    public function test_a_gateway_that_cannot_settle_the_shop_currency_blocks(): void
    {
        $this->shopCurrency('USD', 2);

        $exit = $this->asProduction([
            'payment.vnpay.tmn_code' => 'LIVECODE',
            'payment.vnpay.hash_secret' => 'live-secret',
            'payment.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html',
            'payment.vnpay.api_url' => 'https://merchant.vnpay.vn/merchant_webapi/api/transaction',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('VND', Artisan::output());
    }

    public function test_a_matching_gateway_currency_passes(): void
    {
        $this->shopCurrency('VND', 0);

        $exit = $this->asProduction([
            'payment.vnpay.tmn_code' => 'LIVECODE',
            'payment.vnpay.hash_secret' => 'live-secret',
            'payment.vnpay.payment_url' => 'https://pay.vnpay.vn/vpcpay.html',
            'payment.vnpay.api_url' => 'https://merchant.vnpay.vn/merchant_webapi/api/transaction',
        ]);

        $this->assertSame(0, $exit);
    }

    public function test_a_missing_error_tracker_only_warns(): void
    {
        $exit = $this->asProduction(['sentry.dsn' => null]);

        $this->assertSame(0, $exit, 'Thiếu error tracker là cảnh báo, không phải chặn phát hành.');
        $this->assertStringContainsString('SENTRY_LARAVEL_DSN', Artisan::output());
    }

    /** Outside production the same config is fine — this is a deploy gate, not a linter. */
    public function test_local_is_not_held_to_production_rules(): void
    {
        app()->detectEnvironment(fn () => 'local');

        config([
            'app.debug' => true,
            'queue.default' => 'sync',
            'payment.vnpay.tmn_code' => 'TESTCODE',
            'payment.vnpay.hash_secret' => 'secret',
            'payment.vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        ]);

        $this->assertSame(0, Artisan::call('shop:preflight', ['--env-only' => true]));
    }
}
