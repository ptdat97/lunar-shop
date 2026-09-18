<?php

namespace Tests\Feature;

use App\Models\User;
use Lunar\Core\Models\Cart;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Promotion\Services\LoyaltyService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Giỏ của khách vãng lai phải được nhận về khi họ đăng nhập.
 *
 * Lunar chỉ gán customer lúc **tạo** giỏ. Nhưng đường đi thường gặp nhất của một
 * shop là ngược lại: bỏ hàng vào giỏ trước, đăng nhập sau — và giỏ đó giữ
 * `customer_id` NULL cho tới tận lúc đặt đơn, vì `CheckoutService` mới là chỗ
 * gán.
 *
 * Hỏng ở đây **im lặng và tốn tiền**, theo hai đường khác nhau:
 *
 *  - giảm giá theo hạng thành viên không áp (DiscountManager lọc theo customer
 *    group của giỏ — không có customer thì không có group để khớp);
 *  - ô tiêu điểm thưởng không hiện, nên tính năng trông như chưa bật.
 *
 * Phát hiện ra khi dựng test trình duyệt cho ô tiêu điểm: trang thanh toán
 * không bao giờ render khối đó cho đúng luồng khách thật đi.
 */
class CartClaimedOnLoginTest extends TestCase
{
    use CreatesStorefrontData;

    private function enableLoyalty(): void
    {
        app(Settings::class)->put('loyalty', [
            'enabled' => true, 'earn_per_amount' => 10000, 'point_value' => 200,
            'hold_days' => 14, 'expire_days' => 365, 'min_redeem' => 10, 'max_percent' => 50,
        ]);
    }

    /** Một khách đã có customer và có điểm tiêu được. */
    private function shopperWithPoints(int $points = 500): User
    {
        $user = $this->createUser();
        $customer = app(CustomerResolver::class)->forUser($user);

        $lot = app(LoyaltyService::class)->adjust($customer, $points, 'test fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        return $user;
    }

    private function addToCartAsGuest(): int
    {
        $product = $this->createProduct(['price' => 10000000]);

        return (int) $this->postJson('/api/v1/cart', [
            'variant_id' => $product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful()->json('data.id');
    }

    public function test_a_guest_cart_gains_its_customer_after_login(): void
    {
        $this->seedBaseData();

        $cartId = $this->addToCartAsGuest();
        $this->assertNull(Cart::findOrFail($cartId)->customer_id, 'giỏ vãng lai thì chưa có customer');

        $user = $this->shopperWithPoints();
        $this->actingAs($user);

        $this->getJson('/api/v1/cart')->assertSuccessful();

        $cart = Cart::findOrFail($cartId);
        $this->assertSame($user->id, $cart->user_id);
        $this->assertSame($user->customers()->first()->id, $cart->customer_id);
    }

    public function test_the_points_box_appears_for_a_cart_started_as_a_guest(): void
    {
        $this->seedBaseData();
        $this->enableLoyalty();

        $this->addToCartAsGuest();
        $this->actingAs($this->shopperWithPoints());

        // Đây là triệu chứng mà khách nhìn thấy: khối điểm null = ô tiêu điểm
        // không render ở trang thanh toán, và tính năng trông như chưa bật.
        $this->getJson('/api/v1/cart')
            ->assertSuccessful()
            ->assertJsonPath('data.loyalty.balance', 500);
    }

    public function test_a_cart_already_owned_by_someone_else_is_left_alone(): void
    {
        $this->seedBaseData();

        // Chủ giỏ là tài khoản CHƯA có customer — đó là ca duy nhất chạm tới
        // guard này, vì giỏ đã có customer thì hàm trả về sớm. Giỏ vì thế mang
        // `user_id` của người này nhưng `customer_id` vẫn NULL.
        $owner = $this->createUser();
        $this->actingAs($owner);
        $cartId = $this->addToCartAsGuest();

        $cart = Cart::findOrFail($cartId);
        $this->assertSame($owner->id, $cart->user_id);
        $this->assertNull($cart->customer_id);

        // Máy dùng chung: người thứ hai đăng nhập trên cùng session, và người
        // này CÓ customer. Giỏ của người trước không được biến thành giỏ của họ.
        $intruder = $this->shopperWithPoints();
        $this->actingAs($intruder);

        $this->getJson('/api/v1/cart')->assertSuccessful();

        $cart = Cart::findOrFail($cartId);
        $this->assertSame($owner->id, $cart->user_id, 'giỏ bị đổi chủ');
        $this->assertNull($cart->customer_id, 'giỏ người khác bị gán customer của người vừa đăng nhập');
    }

    public function test_reading_a_cart_never_creates_a_customer_record(): void
    {
        $this->seedBaseData();
        $this->addToCartAsGuest();

        // Tài khoản chưa từng có customer — đọc giỏ không được phép đẻ ra một
        // bản ghi customer; CheckoutService tạo lúc đặt đơn, như trước giờ.
        $user = $this->createUser();
        $this->assertNull(app(CustomerResolver::class)->existingForUser($user));

        $this->actingAs($user);
        $this->getJson('/api/v1/cart')->assertSuccessful();

        $this->assertNull(
            app(CustomerResolver::class)->existingForUser($user->fresh()),
            'đọc giỏ đã tạo customer — đó là tác dụng phụ không ai yêu cầu',
        );
    }
}
