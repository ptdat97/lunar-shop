<?php

namespace Tests\Browser;

use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\CartLine;
use Tests\DuskTestCase;

/**
 * Lookbook "mua cả set": một cú bấm thêm N sản phẩm (roadmap P1 §5).
 *
 * `Tests\Feature\LookbookShopTheSetTest` đã chốt phần DỮ LIỆU: nút mang đúng id
 * SKU, và mỗi id giải ra một SKU thuộc sản phẩm trong set. Phần còn lại nằm trọn
 * trong trình duyệt và không có request nào để mà test:
 *
 *  - enhancer POST **tuần tự** N lần rồi mới dừng — hỏng vòng lặp thì chỉ món
 *    đầu tiên vào giỏ, mà giỏ vẫn hợp lệ nên server không thấy gì bất thường;
 *  - mini-cart phải **tự mở** sau đó, nếu không khách bấm xong không thấy gì xảy
 *    ra và bấm lại lần nữa — nhân đôi cả set;
 *  - dòng trạng thái phải nói `x/y` khi có món trượt (hết hàng), chứ không im
 *    lặng báo thành công.
 *
 * Test này GHI dữ liệu, nên tự dọn (§3.4): xoá mọi giỏ nó tạo ra trong `finally`,
 * kể cả khi phần khẳng định ở giữa ném.
 */
class LookbookShopTheSetTest extends DuskTestCase
{
    /** Id giỏ lớn nhất TRƯỚC khi test chạy — mốc để biết giỏ nào là của nó. */
    private ?int $cartIdBefore = null;

    /** Một lookbook có nút "mua cả set", đọc từ chính trang đã render. */
    private function lookbookWithSet(): array
    {
        foreach (['spring-essentials', 'summer-heat', 'street-style'] as $slug) {
            $html = @file_get_contents(config('app.url').'/lookbooks/'.$slug);

            if ($html && preg_match('/data-sku-ids="([\d,]+)"/', $html, $m)) {
                return [$slug, count(explode(',', $m[1]))];
            }
        }

        $this->markTestSkipped('Không có lookbook nào mang nút "mua cả set" trên DB dev.');
    }

    public function test_one_click_adds_every_item_and_opens_the_drawer(): void
    {
        [$slug, $expected] = $this->lookbookWithSet();

        // Mốc dọn dẹp. Lọc theo "giỏ vãng lai tạo trong 10 phút qua" thì rộng
        // quá — nó xoá luôn giỏ mà chính anh đang mở trên trình duyệt. Chỉ giỏ
        // sinh ra SAU mốc này mới là của test.
        $this->cartIdBefore = (int) Cart::withTrashed()->max('id');

        try {
            $this->browse(function (Browser $browser) use ($slug, $expected) {
                $browser->visit('/lookbooks/'.$slug)
                    ->waitFor('[data-lookbook-add-set]', 15)
                    ->press('[data-lookbook-add-set]')
                    // Chờ ĐỦ số dòng, không chờ dòng đầu tiên: hỏng vòng lặp thì
                    // món đầu vẫn vào giỏ và một phép chờ lỏng sẽ xanh.
                    ->waitUntil(
                        'document.querySelectorAll("#shoppingCart [data-cart-body] [data-line]").length >= '
                        .$expected,
                        25,
                    );

                $lines = $browser->script(
                    'return document.querySelectorAll("#shoppingCart [data-cart-body] [data-line]").length;',
                )[0];

                $this->assertGreaterThanOrEqual(
                    $expected,
                    $lines,
                    "Set có {$expected} món nhưng giỏ chỉ có {$lines} dòng — vòng lặp dừng sớm.",
                );

                // Mini-cart phải tự mở. Không mở thì khách bấm xong không thấy
                // gì và bấm lại — nhân đôi cả set. Offcanvas có animation nên
                // phải CHỜ class `show`, khẳng định ngay là đo lúc nó chưa kịp.
                $browser->waitUntil(
                    'document.getElementById("shoppingCart").classList.contains("show")',
                    10,
                );

                $this->assertTrue(
                    $browser->script(
                        'return document.getElementById("shoppingCart").classList.contains("show");',
                    )[0],
                    'Mini-cart không tự mở sau khi thêm cả set.',
                );

            });
        } finally {
            $this->cleanUp();
        }
    }

    private function cleanUp(): void
    {
        if ($this->cartIdBefore === null) {
            return;
        }

        $ids = Cart::withTrashed()->where('id', '>', $this->cartIdBefore)->pluck('id');

        if ($ids->isNotEmpty()) {
            // Dòng trước giỏ (FK). Và xoá THẲNG khỏi bảng vì Cart dùng
            // SoftDeletes — `delete()` để nguyên hàng, nên lần chạy sau vẫn thấy
            // rác và FK vẫn giữ (§3.7).
            CartLine::whereIn('cart_id', $ids)->delete();
            DB::table('lunar_carts')->whereIn('id', $ids)->delete();
        }

        $this->cartIdBefore = null;
    }
}
