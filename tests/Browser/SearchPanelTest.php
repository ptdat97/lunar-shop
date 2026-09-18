<?php

namespace Tests\Browser;

use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Panel tìm kiếm gợi ý (roadmap P1 §5).
 *
 * Toàn bộ phần đáng hỏng của `enhance/search-panel.js` nằm trong trình duyệt:
 * panel mặc định `hidden`, biểu tượng tìm kiếm là **một link thật tới `/search`**
 * (để không-JS vẫn dùng được), và enhancer chỉ nâng cấp nó bằng `preventDefault`.
 *
 * Nghĩa là nếu `preventDefault` hỏng thì triệu chứng **không phải** panel không
 * mở — mà là **trình duyệt điều hướng đi mất**. Không request nào sai, không log
 * nào lạ, server trả 200 cho cả hai đường. Chỉ có trình duyệt thấy được
 * ([e2e-testing.md §1](../../docs/guides/e2e-testing.md)).
 *
 * Không sửa dữ liệu nào, nên không cần dọn. (Nó vẫn để lại một giỏ rỗng như mọi
 * lượt xem storefront — xem [§3.4](../../docs/guides/e2e-testing.md); đó là
 * `cart_session.auto_create`, không phải thứ test này gây ra.)
 */
class SearchPanelTest extends DuskTestCase
{
    /** Panel hiện/ẩn bằng thuộc tính `hidden`, không phải bằng class. */
    private const VISIBLE = 'document.querySelector("[data-search-panel]")?.hidden === false';

    /** Con trỏ đã nằm trong ô nhập — enhancer focus trong `requestAnimationFrame`. */
    private const FOCUSED = 'document.activeElement === document.querySelector("[data-search-input]")';

    /**
     * Mở panel và chờ tới lúc THẬT SỰ gõ được.
     *
     * Chờ mỗi `hidden === false` là chưa đủ và sinh test chập chờn: cờ đó tắt
     * trước khi element tương tác được, nên `keys()`/`type()` ngay sau đó thỉnh
     * thoảng ném `element not interactable`. Mốc đúng là lúc enhancer focus vào
     * ô nhập — vừa là hàng rào thời gian, vừa là một khẳng định thật (mở panel
     * mà không đặt con trỏ vào ô thì khách phải bấm thêm một lần nữa).
     */
    private function openPanel(Browser $browser): Browser
    {
        return $browser->waitFor('[data-search-toggle]', 15)
            ->click('[data-search-toggle]')
            ->waitUntil(self::VISIBLE, 10)
            ->waitUntil(self::FOCUSED, 10);
    }

    public function test_the_toggle_opens_the_panel_instead_of_navigating(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openPanel($browser->visit('/'));

            // Vẫn ở trang chủ: nút là <a href="/search">, nên không chặn được
            // hành vi mặc định là trang đã đi mất.
            $this->assertStringNotContainsString(
                '/search',
                $browser->driver->getCurrentURL(),
                'Bấm biểu tượng tìm kiếm đã ĐIỀU HƯỚNG thay vì mở panel.',
            );
        });
    }

    public function test_typing_renders_suggestions_from_the_api(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openPanel($browser->visit('/'))
                ->type('[data-search-input]', 'áo')
                // Chờ chính danh sách có mục, không chờ một khoảng thời gian:
                // enhancer debounce 220ms rồi mới gọi API.
                ->waitUntil(
                    'document.querySelectorAll("[data-search-suggestions] li").length > 0',
                    15,
                );

            $count = $browser->script(
                'return document.querySelectorAll("[data-search-suggestions] li a").length;',
            )[0];

            $this->assertGreaterThan(0, $count, 'Không có gợi ý nào được vẽ ra.');

            // Mỗi gợi ý phải là link tới /search có sẵn từ khoá — nếu không thì
            // bấm vào nó chẳng dẫn đi đâu, và cả panel thành đồ trang trí.
            $href = $browser->attribute('[data-search-suggestions] li a', 'href');

            $this->assertStringContainsString('/search?q=', $href);
        });
    }

    public function test_one_character_does_not_call_the_api(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openPanel($browser->visit('/'))
                ->type('[data-search-input]', 'á')
                ->pause(700); // quá hạn debounce 220ms một quãng rộng

            // Ngưỡng MIN_CHARS tồn tại để một ký tự không quét cả catalog. Bỏ nó
            // thì mỗi phím gõ là một truy vấn, và không có gì trên màn hình nói
            // cho ai biết điều đó.
            $empty = $browser->script(
                'return document.querySelectorAll("[data-search-suggestions] li").length === 0;',
            )[0];

            $this->assertTrue($empty, 'Một ký tự vẫn gọi API và vẽ gợi ý.');
        });
    }

    public function test_escape_closes_the_panel(): void
    {
        $this->browse(function (Browser $browser) {
            $this->openPanel($browser->visit('/'));

            // Gửi Esc tới phần tử ĐANG FOCUS, không qua selector.
            //
            // Hai đường kia đều hỏng: `keys('body', …)` đi tìm `body body` vì
            // Dusk tự chèn tiền tố, còn `keys('[data-search-input]', …)` thì
            // chập chờn — WebDriver từ chối gõ vào element nó coi là chưa
            // "displayed", và panel có transition nên có một khoảng ô nhập đã
            // được focus mà hộp vẫn đang mở ra. Đo được: hỏng khoảng 1/5 lần.
            //
            // Cách này cũng đúng với thao tác thật hơn: người dùng bấm Esc, họ
            // không nhắm vào một selector nào cả.
            $browser->driver->action()->sendKeys(null, WebDriverKeys::ESCAPE)->perform();

            $browser->waitUntil('document.querySelector("[data-search-panel]")?.hidden === true', 10);

            $this->assertTrue(
                $browser->script('return document.querySelector("[data-search-panel]").hidden;')[0],
                'Esc không đóng panel.',
            );
        });
    }
}
