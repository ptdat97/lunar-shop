<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Lunar\Core\Models\Product;
use Tests\DuskTestCase;

/**
 * Báo hàng về: hợp đồng sự kiện giữa hai enhancer (roadmap P1 §5).
 *
 * `enhance/notify-me.js` không tự biết khách đang chọn biến thể nào — nó **nghe**
 * `variant:changed` mà `enhance/product-variant.js` bắn ra, và chỉ hiện form khi
 * `detail.inStock === false`.
 *
 * Đó là một hợp đồng giữa hai file JS, không đi qua server một lần nào: tên sự
 * kiện, hình dạng `detail`, và phần tử nào bắn. Đổi bất kỳ mảnh nào trong đó thì
 * **không có request nào sai, không có log nào lạ** — form báo hàng về đơn giản
 * là không bao giờ hiện nữa, và không ai biết cho tới khi hết hàng thật.
 *
 * Test ghim hợp đồng từ **cả hai đầu**, và cố ý làm vậy mà **không ghi gì vào DB
 * dev**: sửa tồn kho về 0 rồi khôi phục là một bút toán kho giả nằm lại sổ, đổi
 * lấy một điều mà một sự kiện tổng hợp chứng minh sạch sẽ hơn.
 *
 *  - *đầu phát*: chọn biến thể thật trên trang → có `variant:changed` mang
 *    `inStock`;
 *  - *đầu nghe*: bắn `variant:changed` với `inStock: false` → form hiện ra và
 *    mang đúng id biến thể.
 */
class NotifyMeTest extends DuskTestCase
{
    /** Hộp có mặt trong DOM (kể cả khi đang `hidden`). */
    private const EXISTS = 'document.querySelector("[data-notify-me]") !== null';

    /** Trang sản phẩm có nhiều lựa chọn biến thể để bấm. */
    private function productWithOptions(): string
    {
        $product = Product::query()->with(['defaultUrl', 'variants'])->get()
            ->first(fn (Product $p) => $p->defaultUrl !== null && $p->variants->count() > 1);

        $this->assertNotNull($product, 'Không có sản phẩm nhiều biến thể để thử.');

        return '/products/'.$product->defaultUrl->slug;
    }

    public function test_the_variant_picker_really_emits_the_event(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit($this->productWithOptions())
                ->waitFor('[data-product-detail]', 15)
                ->waitFor('[data-option]', 15);

            // Ghi lại mọi sự kiện trước khi bấm.
            $browser->script(<<<'JS'
                window.__variantEvents = [];
                document.addEventListener('variant:changed', (e) => {
                    window.__variantEvents.push(e.detail);
                });
            JS);

            // Bấm một lựa chọn bất kỳ còn bấm được.
            $browser->script('document.querySelector("[data-option]:not([disabled])").click();');

            $browser->waitUntil('window.__variantEvents.length > 0', 10);

            $detail = $browser->script('return window.__variantEvents[0];')[0];

            // `inStock` là khoá mà notify-me đọc. Nó phải CÓ MẶT (kể cả true),
            // vì `undefined` lặng lẽ rơi vào nhánh "còn hàng" và form không bao
            // giờ hiện.
            $this->assertArrayHasKey('inStock', $detail, 'Sự kiện thiếu khoá `inStock`.');
            $this->assertArrayHasKey('variant', $detail, 'Sự kiện thiếu khoá `variant`.');
        });
    }

    public function test_an_out_of_stock_variant_reveals_the_form(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit($this->productWithOptions())
                ->waitFor('[data-product-detail]', 15)
                // `waitFor` chờ element HIỆN RA, và bản Dusk này không có
                // `waitForPresence`. Hộp luôn mang `hidden` tới khi JS mở nó,
                // nên chờ bằng chính phép kiểm tồn tại trong DOM.
                ->waitUntil(self::EXISTS, 15);

            // Mặc định còn hàng → hộp phải đang ẩn.
            $this->assertTrue(
                $browser->script('return document.querySelector("[data-notify-me]").hidden;')[0],
                'Hộp báo hàng về hiện sẵn khi hàng vẫn còn.',
            );

            // Bắn đúng sự kiện mà product-variant.js bắn, với hàng đã hết.
            $browser->script(<<<'JS'
                document.querySelector('[data-product-detail]').dispatchEvent(
                    new CustomEvent('variant:changed', {
                        bubbles: true,
                        detail: { variant: { id: 424242 }, inStock: false, allChosen: true },
                    }),
                );
            JS);

            $browser->waitUntil('document.querySelector("[data-notify-me]").hidden === false', 10);

            // Và phải mang đúng id — form gửi đi id nào là chuyện của cả tính năng.
            $this->assertSame(
                '424242',
                $browser->script('return document.querySelector("[data-notify-variant]").value;')[0],
                'Form không nhận id biến thể từ sự kiện.',
            );
        });
    }

    public function test_choosing_an_in_stock_variant_hides_it_again(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit($this->productWithOptions())
                ->waitUntil(self::EXISTS, 15);

            $fire = fn (string $inStock) => <<<JS
                document.querySelector('[data-product-detail]').dispatchEvent(
                    new CustomEvent('variant:changed', {
                        bubbles: true,
                        detail: { variant: { id: 1 }, inStock: {$inStock}, allChosen: true },
                    }),
                );
            JS;

            $browser->script($fire('false'));
            $browser->waitUntil('document.querySelector("[data-notify-me]").hidden === false', 10);

            // Đổi sang biến thể còn hàng thì hộp phải biến đi. Không ẩn lại là
            // mời khách đăng ký nhận báo cho thứ đang bán được ngay.
            $browser->script($fire('true'));
            $browser->waitUntil('document.querySelector("[data-notify-me]").hidden === true', 10);

            $this->assertTrue(
                $browser->script('return document.querySelector("[data-notify-me]").hidden;')[0],
                'Hộp không ẩn lại khi biến thể còn hàng.',
            );
        });
    }
}
