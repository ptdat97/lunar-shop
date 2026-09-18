<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Modules\Assets\Services\MediaSettings;
use Modules\Assets\Services\MediaUrl;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * `<picture>` responsive trên storefront (roadmap P1 §5).
 *
 * **Cố ý KHÔNG phải test trình duyệt.** `components/picture.blade.php` không có
 * một dòng JS nào — nó là HTML server render ra, nên theo bảng quyết định ở
 * [e2e-testing.md §1](../../docs/guides/e2e-testing.md) bằng chứng nằm ở
 * "HTML server trả về" → test feature. Viết Dusk cho nó là đắt hơn, giòn hơn, và
 * khi đỏ thì nói ít hơn.
 *
 * Phần duy nhất thuộc trình duyệt — ảnh trong `srcset` có tải được hay 404 —
 * `StorefrontSmokeTest` đã canh: nó bắt **mọi** dòng browser log trên 12 trang
 * chính, và một ảnh 404 là một dòng trong đó.
 *
 * Cái đáng canh ở đây là hợp đồng markup, và nó im lặng khi hỏng: trình duyệt
 * không báo gì khi thiếu `srcset` (chỉ tải ảnh to hơn cần thiết), cũng không báo
 * gì khi thiếu `width`/`height` (chỉ giật layout). Không đo thì không ai biết.
 */
class ResponsivePictureTest extends TestCase
{
    use CreatesStorefrontData;

    /** Render component với payload cho sẵn, không cần dựng cả trang. */
    private function render(array $data): string
    {
        return view('theme::components.picture', $data)->render();
    }

    public function test_the_webp_source_comes_before_the_fallback(): void
    {
        $html = $this->render([
            'picture' => [
                'src' => '/media/1/a-medium.jpg',
                'srcset' => '/media/1/a-small.jpg 400w, /media/1/a-medium.jpg 800w',
                'webp' => '/media/1/a-webp.webp',
                'width' => 800,
                'height' => 1200,
            ],
            'alt' => 'Áo linen',
        ]);

        $webp = strpos($html, 'type="image/webp"');
        $fallback = strpos($html, '<source srcset=');

        $this->assertNotFalse($webp, 'thiếu <source> webp');
        $this->assertNotFalse($fallback, 'thiếu <source> srcset thường');

        // Trình duyệt lấy <source> ĐẦU TIÊN khớp. Đảo thứ tự thì không ai được
        // phục vụ webp nữa, và không có triệu chứng nào ngoài băng thông.
        $this->assertLessThan($fallback, $webp, 'webp phải đứng TRƯỚC nguồn thường');
    }

    public function test_width_and_height_are_emitted_to_stop_layout_shift(): void
    {
        $html = $this->render([
            'picture' => ['src' => '/media/1/a.jpg', 'srcset' => '', 'webp' => null, 'width' => 800, 'height' => 1200],
        ]);

        $this->assertStringContainsString('width="800"', $html);
        $this->assertStringContainsString('height="1200"', $html);
    }

    public function test_a_payload_without_dimensions_omits_them_rather_than_writing_zero(): void
    {
        $html = $this->render([
            'picture' => ['src' => '/media/1/a.jpg', 'srcset' => '', 'webp' => null, 'width' => 0, 'height' => 0],
        ]);

        // `width="0"` co ảnh lại thành vô hình. Không biết thì đừng nói.
        $this->assertStringNotContainsString('width="0"', $html);
        $this->assertStringNotContainsString('height="0"', $html);
    }

    public function test_no_payload_renders_nothing_at_all(): void
    {
        // Không có ảnh thì KHÔNG được render <picture> rỗng hay <img src="">:
        // một img rỗng là một request hỏng và một ô vỡ trên trang.
        $this->assertSame('', trim($this->render(['picture' => null])));
        $this->assertSame('', trim($this->render(['picture' => ['src' => null]])));
    }

    public function test_lazy_by_default_and_eager_when_asked(): void
    {
        $payload = ['src' => '/media/1/a.jpg', 'srcset' => '', 'webp' => null, 'width' => 0, 'height' => 0];

        $this->assertStringContainsString('loading="lazy"', $this->render(['picture' => $payload]));

        // Ảnh đầu màn hình phải eager + fetchpriority, nếu không LCP trả giá.
        $eager = $this->render(['picture' => $payload, 'loading' => 'eager', 'fetchpriority' => 'high']);
        $this->assertStringContainsString('loading="eager"', $eager);
        $this->assertStringContainsString('fetchpriority="high"', $eager);
    }

    public function test_the_alt_text_is_escaped(): void
    {
        $html = $this->render([
            'picture' => ['src' => '/media/1/a.jpg', 'srcset' => '', 'webp' => null, 'width' => 0, 'height' => 0],
            'alt' => '"><script>alert(1)</script>',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
    }

    // ------------------------------------------------- payload từ MediaUrl

    public function test_the_srcset_is_ordered_smallest_first_with_real_widths(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct();
        $product->addMedia(UploadedFile::fake()->image('a.jpg', 1200, 1800))->toMediaCollection('images');

        $payload = app(MediaUrl::class)->responsive($product->fresh()->thumbnail);

        $this->assertNotNull($payload, 'không dựng được payload responsive');

        preg_match_all('/(\d+)w/', $payload['srcset'], $matches);
        $widths = array_map('intval', $matches[1]);

        $this->assertNotEmpty($widths, 'srcset không có mô tả độ rộng nào');

        $sorted = $widths;
        sort($sorted);

        // Không sắp xếp thì trình duyệt vẫn chọn đúng, nhưng `ksort` trong
        // MediaUrl tồn tại là có lý do: srcset lộn xộn là thứ không ai đọc nổi
        // khi đi truy một ảnh sai cỡ.
        $this->assertSame($sorted, $widths, 'srcset phải xếp từ nhỏ tới lớn');

        // Và các độ rộng phải là kích thước ĐANG cấu hình, không phải số cứng.
        $configured = array_column(app(MediaSettings::class)->sizes(), 'width');
        foreach ($widths as $width) {
            $this->assertContains($width, $configured, "độ rộng {$width}w không có trong cấu hình");
        }
    }

    public function test_a_product_card_renders_a_real_picture_element(): void
    {
        $this->seedBaseData();

        $product = $this->createProduct(['name' => 'Linen Shirt']);
        $product->addMedia(UploadedFile::fake()->image('a.jpg', 1200, 1800))->toMediaCollection('images');

        // Đi qua trang thật: view composer trong AssetsServiceProvider mới là
        // chỗ dựng `picture`, và đó là mắt xích dễ đứt nhất (§7 cấm Blade tự
        // resolve service, nên thiếu composer = không có ảnh, không có lỗi).
        $html = $this->get('/products/'.$product->fresh()->defaultUrl->slug)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<picture>', $html);
        $this->assertStringContainsString('srcset=', $html);
    }
}
