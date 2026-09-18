<?php

namespace Tests\Browser;

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
 * Chỉ đọc, không ghi gì — chạy lại bao nhiêu lần trên DB dev cũng an toàn.
 */
class SearchPanelTest extends DuskTestCase
{
    /** Panel hiện/ẩn bằng thuộc tính `hidden`, không phải bằng class. */
    private const VISIBLE = 'document.querySelector("[data-search-panel]")?.hidden === false';

    public function test_the_toggle_opens_the_panel_instead_of_navigating(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitFor('[data-search-toggle]', 15)
                ->click('[data-search-toggle]')
                ->waitUntil(self::VISIBLE, 10);

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
            $browser->visit('/')
                ->waitFor('[data-search-toggle]', 15)
                ->click('[data-search-toggle]')
                ->waitUntil(self::VISIBLE, 10)
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
            $browser->visit('/')
                ->waitFor('[data-search-toggle]', 15)
                ->click('[data-search-toggle]')
                ->waitUntil(self::VISIBLE, 10)
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
            $browser->visit('/')
                ->waitFor('[data-search-toggle]', 15)
                ->click('[data-search-toggle]')
                ->waitUntil(self::VISIBLE, 10)
                // KHÔNG gửi vào 'body': Dusk tự chèn tiền tố `body ` vào selector
                // nên nó đi tìm `body body`. Ô nhập cũng đúng là nơi con trỏ
                // đang nằm — enhancer focus vào đó ngay khi mở panel.
                ->keys('[data-search-input]', ['{escape}'])
                ->waitUntil('document.querySelector("[data-search-panel]")?.hidden === true', 10);

            $this->assertTrue(
                $browser->script('return document.querySelector("[data-search-panel]").hidden;')[0],
                'Esc không đóng panel.',
            );
        });
    }
}
