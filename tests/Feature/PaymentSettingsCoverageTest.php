<?php

namespace Tests\Feature;

use Modules\Checkout\Panel\PaymentSettings;
use Modules\Core\Support\Settings;
use Tests\TestCase;

/**
 * Every payment setting a gateway reads must be editable in the panel.
 *
 * `vnpay.api_url` and `momo.refund_url` were not. Both are the REFUND
 * endpoints, and both fall back to config — which defaults to sandbox. So an
 * admin who moved the shop to production through the settings page moved
 * payments and left refunds pointing at the test gateway: real money in, refund
 * calls into a sandbox that never held it.
 *
 * `shop:preflight` catches this at deploy, but not an admin editing settings
 * afterwards — and settings editing is exactly how a shop goes live.
 */
class PaymentSettingsCoverageTest extends TestCase
{
    /** @return list<string> keys the gateways read, e.g. `vnpay.api_url` */
    private function keysReadByGateways(): array
    {
        $keys = [];

        foreach (glob(base_path('modules/Checkout/app/Services/*.php')) as $file) {
            preg_match_all(
                "/get\(\s*'payment\.((?:vnpay|momo)\.[a-z0-9_]+)'/i",
                (string) file_get_contents($file),
                $matches,
            );

            $keys = array_merge($keys, $matches[1]);
        }

        return array_values(array_unique($keys));
    }

    public function test_every_key_a_gateway_reads_has_a_panel_field(): void
    {
        $declared = array_map(
            fn ($field) => $field->name,
            (new PaymentSettings)->fields(),
        );

        $read = $this->keysReadByGateways();

        $this->assertNotEmpty($read, 'Không tìm thấy khoá nào — regex có thể đã lệch khỏi code.');

        foreach ($read as $key) {
            $this->assertContains(
                $key,
                $declared,
                "Gateway đọc [payment.{$key}] nhưng trang cài đặt không có trường nào sửa nó — "
                    .'nó sẽ mãi nằm ở giá trị config, mà mặc định config là SANDBOX.',
            );
        }
    }

    /** The whole point: the panel must be able to move refunds off the sandbox. */
    public function test_the_refund_endpoints_can_be_moved_to_production(): void
    {
        $settings = app(Settings::class);
        $before = $settings->group('payment');

        (new PaymentSettings)->persist([
            'default' => 'cod',
            'vnpay' => ['api_url' => 'https://merchant.vnpay.vn/merchant_webapi/api/transaction'],
            'momo' => ['refund_url' => 'https://payment.momo.vn/v2/gateway/api/refund'],
        ]);
        $settings->forgetCache();

        $this->assertStringNotContainsString('sandbox', (string) $settings->get('payment.vnpay.api_url'));
        $this->assertStringNotContainsString('test-payment', (string) $settings->get('payment.momo.refund_url'));

        $settings->put('payment', $before);
        $settings->forgetCache();
    }
}
