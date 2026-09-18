<?php

namespace Tests\Browser;

use Illuminate\Support\Facades\File;
use Laravel\Dusk\Browser;
use Lunar\Core\Models\Product;
use Modules\Catalog\Models\Review;
use Tests\DuskTestCase;

/**
 * Gửi đánh giá KÈM ẢNH từ trình duyệt thật (roadmap §17).
 *
 * `ReviewPhotoTest` (Feature) đã chốt hợp đồng phía server: multipart được nhận,
 * ảnh buộc phải qua duyệt, nhãn đã-mua-hàng do server tra. Nhưng **không test
 * server nào chứng minh được form thật sự gửi file đi**: `enhance/review-form.js`
 * phải tự quyết định đóng gói JSON hay `FormData` tuỳ có ảnh hay không, và phép
 * quyết định đó chạy trong trình duyệt, ở thời điểm PHPUnit không nhìn thấy.
 *
 * Đây đúng là lớp lỗi mà [e2e-testing.md §1](../../docs/guides/e2e-testing.md)
 * gọi là "ca đắt nhất", và trớ trêu thay nó cũng xảy ra trên chính form này: bản
 * trước dựng `action` từ slug trong khi route bind theo id, nên **mọi lượt gửi
 * đánh giá đều 404** mà test vẫn xanh — vì nó kiểm *form có mặt* chứ không kiểm
 * *form gửi được*.
 *
 * Nên test này cố ý KHÔNG khẳng định "ô chọn ảnh có hiển thị". Nó bấm gửi thật,
 * rồi đòi hai bằng chứng cùng lúc:
 *
 *  1. dòng chữ trả về là lời nhắn **chờ duyệt**, không phải "cảm ơn, đã đăng" —
 *     chứng minh JS đọc được `meta.pending` server trả về;
 *  2. trong DB có một đánh giá mang **đúng một ảnh** — chứng minh file thật sự
 *     đi theo request, tức `FormData` đã được dùng.
 *
 * Chạy trên DB dev nên tự dọn (§3.4): xoá đánh giá vừa tạo trong `finally`.
 */
class ReviewPhotoUploadTest extends DuskTestCase
{
    /** Tên người viết dùng để tìm lại đúng bản ghi test này tạo ra. */
    private string $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = 'Dusk '.uniqid();
    }

    public function test_a_photo_actually_reaches_the_server_and_the_reply_says_pending(): void
    {
        if (! config('review.photos.enabled', true)) {
            $this->markTestSkipped('Ảnh đánh giá đang tắt ở môi trường này.');
        }

        $product = Product::query()->with('defaultUrl')->get()
            ->first(fn (Product $p) => $p->defaultUrl !== null);

        $this->assertNotNull($product, 'Không có sản phẩm nào có URL để mở trang chi tiết.');

        $photo = $this->temporaryPhoto();

        try {
            $this->browse(function (Browser $browser) use ($product, $photo) {
                $browser->visit('/products/'.$product->defaultUrl->slug);

                // Nút mở popup nằm ở cuối mục đánh giá, tức góc dưới-phải của
                // trang — đúng chỗ thanh Laravel Debugbar đứng. Click thẳng sẽ
                // bị nó chặn (`ElementClickInterceptedException`), nên đưa nút
                // ra giữa khung nhìn rồi mới click. Vẫn là click thật, không
                // phải gọi `.click()` bằng script: thứ đang được kiểm ở đây là
                // "khách bấm được nút mở popup".
                $browser->script(
                    'document.querySelector(\'button[data-bs-target="#reviewForm"]\')'
                    .'.scrollIntoView({block: "center"});'
                );

                $browser
                    // Form nằm trong popup #reviewForm, nên phải MỞ popup trước:
                    // type/attach/press của Dusk chỉ làm việc được với phần tử
                    // đang hiển thị, còn `waitFor` thì chờ đúng trạng thái đó.
                    ->click('button[data-bs-target="#reviewForm"]')
                    ->waitFor('[data-review-form]', 15)
                    ->type('[data-review-form] [name="author"]', $this->author)
                    ->select('[data-review-form] [name="rating"]', '5')
                    ->type('[data-review-form] [name="body"]', 'Gửi từ Dusk.')
                    ->attach('[data-review-form] input[type="file"]', $photo)
                    ->press('[data-review-form] button[type="submit"]')
                    // Chờ đúng dòng trạng thái có chữ, chứ không chờ một khoảng
                    // thời gian: request có ảnh chậm hơn hẳn request JSON.
                    ->waitUntil(
                        'document.querySelector("[data-review-status]").textContent.trim().length > 0',
                        20,
                    );

                $status = trim($browser->text('[data-review-status]'));

                // Bằng chứng 1: JS đọc `meta.pending`. Nếu nó vẫn in câu "cảm ơn,
                // đã đăng" thì khách bị nói dối — đánh giá đang nằm chờ duyệt.
                $this->assertSame(
                    __('storefront.product.reviews_pending'),
                    $status,
                    'Form phải báo CHỜ DUYỆT khi đánh giá có ảnh, không phải báo đã đăng.',
                );
            });

            // Bằng chứng 2: file đi theo request thật. Đây là phần mà chỉ trình
            // duyệt mới chứng minh được — `FormData` được dựng trong JS.
            $review = Review::where('author', $this->author)->first();

            $this->assertNotNull($review, 'Không có đánh giá nào được ghi — form không gửi được.');
            $this->assertFalse((bool) $review->approved, 'Đánh giá có ảnh phải nằm chờ duyệt.');
            $this->assertCount(
                1,
                $review->getMedia(Review::PHOTOS),
                'Đánh giá được ghi nhưng KHÔNG có ảnh — JS đã gửi JSON thay vì FormData.',
            );
        } finally {
            File::delete($photo);

            // Xoá luôn media kèm theo (spatie dọn khi model bị xoá).
            Review::where('author', $this->author)->get()->each->delete();
        }
    }

    /**
     * Một file JPEG thật trên đĩa.
     *
     * Phải là ảnh thật chứ không phải file đặt tên `.jpg`: request đi qua luật
     * `mimetypes` đọc NỘI DUNG, nên một file giả sẽ bị chặn ở 422 và test sẽ đỏ
     * vì lý do không liên quan tới thứ nó đang kiểm.
     */
    private function temporaryPhoto(): string
    {
        $path = storage_path('app/dusk-review-'.uniqid().'.jpg');

        $image = imagecreatetruecolor(320, 420);
        imagefill($image, 0, 0, imagecolorallocate($image, 190, 170, 150));
        imagejpeg($image, $path, 80);
        imagedestroy($image);

        return $path;
    }
}
