<?php

namespace Tests\Feature;

use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Price;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\Region;
use Modules\Checkout\Services\TokenAwareCartSession;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Giỏ của client stateless phải sinh ra trong CÙNG ngữ cảnh với giỏ web.
 *
 * `TokenAwareCartSession` ghi đè `createNewCart()` để client bearer-token có giỏ
 * riêng (Lunar đọc sai guard nên giỏ token bị mint mới mỗi request). Nhưng bản
 * ghi đè dựng `Cart::create()` bằng tay, trong khi bản của Lunar đi qua
 * `ResolveStorefrontContext` — thứ mà chính Lunar gọi là *"the single home for
 * the storefront default cascade"*.
 *
 * Chênh lệch đo được ở **ba trường**:
 *
 * | | Giỏ web (Lunar) | Giỏ token (bản ghi đè cũ) |
 * | --- | --- | --- |
 * | `region_id` | region mặc định | **NULL** |
 * | `channel_id` | channel CỦA REGION → mặc định | mặc định toàn cục |
 * | `currency_id` | currency CỦA REGION → mặc định | mặc định toàn cục |
 *
 * Shop hiện tại một region, và region đó trỏ đúng vào channel/currency mặc định,
 * nên **hôm nay không có triệu chứng gì** — đúng lớp lỗi mà nguyên tắc số 0 mô
 * tả: *chạy đúng cho tới ngày không đúng nữa*. `region_id` còn được
 * `FillOrderFromCart` chép thẳng sang `lunar_orders.region_id`, nên đơn đặt từ
 * app mang region rỗng trong khi đơn từ web thì không.
 *
 * Test dựng đúng ngày "không đúng nữa": cho region mặc định trỏ sang một currency
 * KHÁC mặc định toàn cục, rồi đòi giỏ token đi theo region.
 */
class StatelessCartContextTest extends TestCase
{
    use CreatesStorefrontData;

    /** Headers khiến request được coi là stateless (khách app chưa có handle). */
    private const APP = [TokenAwareCartSession::CLIENT_HEADER => 'app'];

    private function addToCart(array $headers = [], ?Product $product = null): int
    {
        $product ??= $this->createProduct();

        return (int) $this->postJson(
            '/api/v1/cart',
            ['variant_id' => $product->variants->first()->id, 'quantity' => 1],
            $headers,
        )->assertSuccessful()->json('data.id');
    }

    public function test_a_stateless_cart_carries_the_region_like_a_web_cart_does(): void
    {
        $this->seedBaseData();

        $region = Region::getDefault();
        $this->assertNotNull($region, 'shop không có region mặc định — phép kiểm này vô nghĩa');

        $cart = Cart::findOrFail($this->addToCart(self::APP));

        // `FillOrderFromCart` chép thẳng cột này sang đơn, nên để null ở đây là
        // để null trên mọi đơn đặt từ app.
        $this->assertSame(
            $region->id,
            $cart->region_id,
            'giỏ stateless không mang region — đơn đặt từ app sẽ có region rỗng',
        );
    }

    public function test_the_stateless_cart_follows_the_region_cascade_not_the_global_default(): void
    {
        $this->seedBaseData();

        // Ngày "không đúng nữa": region mặc định trỏ sang một currency khác
        // currency mặc định toàn cục.
        $other = Currency::create([
            'code' => 'VND',
            'name' => 'Vietnamese Dong',
            'exchange_rate' => 1,
            'decimal_places' => 0,
            'default' => false,
            'enabled' => true,
        ]);

        Region::getDefault()->update(['currency_id' => $other->id]);

        // Sản phẩm phải có giá ở CẢ currency kia, nếu không Lunar ném
        // MissingCurrencyPriceException và test đỏ vì thiếu fixture chứ không
        // phải vì cascade sai.
        $product = $this->createProduct();
        Price::create([
            'price' => 250000,
            'currency_id' => $other->id,
            'priceable_type' => $product->variants->first()->getMorphClass(),
            'priceable_id' => $product->variants->first()->id,
        ]);

        $cart = Cart::findOrFail($this->addToCart(self::APP, $product));

        // Lunar cho currency của REGION thắng mặc định toàn cục
        // (ResolveStorefrontContext). Giỏ token phải theo cùng luật, nếu không
        // cùng một giỏ hàng được tính bằng hai đơn vị tiền khác nhau tuỳ khách
        // mở app hay mở web.
        $this->assertSame(
            $other->id,
            $cart->currency_id,
            'giỏ stateless dùng currency mặc định toàn cục thay vì currency của region',
        );
    }

    public function test_a_web_cart_and_a_stateless_cart_agree(): void
    {
        $this->seedBaseData();

        $web = Cart::findOrFail($this->addToCart());
        $app = Cart::findOrFail($this->addToCart(self::APP));

        $this->assertNotSame($web->id, $app->id, 'hai đường phải cho hai giỏ khác nhau');

        // Cùng một shop thì hai cửa vào không được sinh ra hai ngữ cảnh khác nhau.
        $this->assertSame(
            [$web->region_id, $web->channel_id, $web->currency_id],
            [$app->region_id, $app->channel_id, $app->currency_id],
        );
    }

    public function test_the_stateless_cart_still_gets_its_own_handle(): void
    {
        $this->seedBaseData();

        // Lý do bản ghi đè tồn tại ngay từ đầu — đừng đánh mất nó khi sửa ngữ cảnh.
        $cart = Cart::findOrFail($this->addToCart(self::APP));

        $this->assertNotNull($cart->public_token, 'giỏ stateless mất handle để client claim lại');
    }
}
