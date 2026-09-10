<?php

namespace Tests\Feature;

use Modules\Catalog\Services\PricingService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The value reported to analytics must be a number, not a scraped string.
 *
 * `pixels.js` used to pull the price out of the RENDERED text with
 * `parseFloat(text.replace(/[^0-9.]/g, ''))`. That only works for US
 * formatting. Measured against real formats:
 *
 *   "250.000 ₫"    → 250      (1.000× too small)
 *   "1.250.000 ₫"  → 1.25     (1.000.000× too small)
 *   "2.500,00 US$" → 2.5      (1.000× too small)
 *
 * Nothing errors. The shop just reports conversion values off by a factor of a
 * thousand to GA and Facebook, which corrupts ROAS and the ad bidding built on
 * it. The currency was hardcoded to USD on top of that.
 *
 * The fix is to put the raw amount in the DOM, so this asserts the attributes
 * are there and carry a machine-readable number — the JS side is covered by
 * the browser test.
 */
class PixelValueTest extends TestCase
{
    use CreatesStorefrontData;

    public function test_the_card_carries_a_machine_readable_price(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'ao-len']);

        $html = $this->get(route('storefront.product', $product->defaultUrl->slug))
            ->assertOk()
            ->getContent();

        preg_match('/data-price-amount="([^"]*)"/', $html, $amount);
        preg_match('/data-price-currency="([^"]*)"/', $html, $currency);

        $this->assertNotEmpty($amount[1] ?? '', 'Thẻ giá không mang data-price-amount.');
        $this->assertIsNumeric($amount[1], 'data-price-amount phải là số thô, không phải chuỗi đã định dạng.');

        $this->assertSame(
            app(PricingService::class)->defaultCurrencyCode(),
            $currency[1] ?? null,
            'Mã tiền tệ trong DOM không khớp tiền tệ của shop.',
        );
    }

    /**
     * The regression itself: the rendered price string must NOT be what a
     * tracker reads, because parsing it back is what was wrong.
     */
    public function test_the_raw_amount_and_the_formatted_string_are_separate(): void
    {
        $this->seedBaseData();
        $product = $this->createProduct(['slug' => 'chan-vay']);

        $html = $this->get(route('storefront.product', $product->defaultUrl->slug))->getContent();

        preg_match('/data-price-amount="([^"]*)"[^>]*>([^<]*)</', $html, $m);

        $raw = $m[1] ?? '';
        $shown = trim($m[2] ?? '');

        $this->assertIsNumeric($raw);
        $this->assertNotSame($raw, $shown, 'Số thô và chuỗi hiển thị đang là một — nghĩa là chưa tách.');

        // Chính phép bóc cũ, chạy trên chuỗi hiển thị: phải KHÁC số thô, đó là
        // toàn bộ lý do bản sửa này tồn tại.
        $scraped = (float) preg_replace('/[^0-9.]/', '', $shown);

        $this->assertNotSame(
            (float) $raw,
            $scraped,
            'Định dạng hiện tại tình cờ bóc ra đúng số — hãy đổi sang định dạng có dấu chấm phân nhóm để test có nghĩa.',
        );
    }
}
