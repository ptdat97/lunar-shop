<?php

namespace Tests\Feature;

use App\Models\User;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Lunar\Core\Models\OrderLine;
use Lunar\Core\Models\Product;
use Modules\Checkout\Services\CartService;
use Modules\Core\Support\Settings;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Support\OrderStatus;
use Modules\Promotion\Models\LoyaltyEntry;
use Modules\Promotion\Services\LoyaltyService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Điểm thưởng (docs/roadmap.md §16).
 *
 * Roadmap tự cảnh báo: phần đắt không phải CỘNG điểm mà là TIÊU điểm, vì nó
 * chạm vào tính tiền, hoàn tiền và huỷ đơn. Bố cục test đi theo đúng ba mặt đó.
 *
 * Bất biến xuyên suốt: **số dư là SUM của sổ cái**, không phải một cột. Mọi
 * khẳng định dưới đây đọc số dư qua `LoyaltyService::balanceFor()` để nếu ai đó
 * thêm một cột số dư thì test này vẫn đang kiểm sổ, không kiểm cái cột đó.
 */
class LoyaltyPointsTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private Product $product;

    private Customer $customer;

    private function loyalty(): LoyaltyService
    {
        return app(LoyaltyService::class);
    }

    private function enableLoyalty(array $overrides = []): void
    {
        app(Settings::class)->put('loyalty', [
            'enabled' => true,
            'earn_per_amount' => 10000,
            'point_value' => 200,
            'hold_days' => 14,
            'expire_days' => 365,
            'min_redeem' => 10,
            'max_percent' => 50,
            ...$overrides,
        ]);
    }

    private function setUpShop(): void
    {
        $this->seedBaseData();
        // 10.000.000 đơn vị NHỎ = 100.000 tiền lớn (factor 100). Đủ to để trần
        // 50% (= 250 điểm ở mức 200₫/điểm) không phải thứ chặn các test tiêu
        // điểm bên dưới — trần đó có test riêng của nó.
        $this->product = $this->createProduct(['name' => 'Linen Shirt', 'price' => 10000000]);
        $this->customer = Customer::factory()->create();
    }

    /** Một đơn của khách đang xét, tổng tiền tự chọn (đơn vị NHỎ). */
    private function order(int $total = 10000000, string $status = 'payment-received', array $meta = []): Order
    {
        $order = Order::factory()->create([
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            'customer_id' => $this->customer->id,
            ...$this->orderAttributesFor($status, $meta),
            'sub_total' => $total, 'discount_total' => 0, 'shipping_total' => 0,
            'tax_total' => 0, 'total' => $total,
        ]);

        OrderLine::factory()->create([
            'order_id' => $order->id,
            'purchasable_type' => 'product_variant',
            'purchasable_id' => $this->product->variants->first()->id,
            'type' => 'physical',
            'description' => 'Linen Shirt',
            'quantity' => 1, 'unit_price' => $total, 'unit_quantity' => 1,
            'sub_total' => $total, 'discount_total' => 0, 'tax_total' => 0, 'total' => $total,
        ]);

        return $order->refresh();
    }

    private function shopper(): User
    {
        $user = $this->createUser();
        $user->customers()->attach($this->customer->id);

        return $user;
    }

    // -------------------------------------------------------------------- cộng

    public function test_points_are_earned_on_what_was_actually_paid(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        // factor 100 → 10.000.000 đơn vị nhỏ = 100.000 tiền lớn.
        // 100.000 / 10.000 = 10 điểm.
        $order = $this->order(10000000);

        $this->loyalty()->earnFor($order);

        $this->assertSame(10, (int) LoyaltyEntry::where('type', LoyaltyEntry::EARN)->sum('points'));
    }

    public function test_earned_points_wait_out_the_returns_window_before_they_can_be_spent(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $this->loyalty()->earnFor($this->order());

        // Đã ghi vào sổ, nhưng CHƯA tiêu được — đây là "thưởng sau hạn đổi/trả"
        // được mã hoá bằng một cột ngày, không bằng một lệnh phát thưởng.
        $this->assertSame(0, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(10, $this->loyalty()->pendingFor($this->customer));

        $this->travel(15)->days();

        $this->assertSame(10, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(0, $this->loyalty()->pendingFor($this->customer));
    }

    public function test_the_same_order_never_earns_twice(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $order = $this->order();

        // Callback cổng thanh toán vào hai lần là chuyện bình thường.
        $this->loyalty()->earnFor($order);
        $this->loyalty()->earnFor($order);

        $this->assertSame(1, LoyaltyEntry::where('type', LoyaltyEntry::EARN)->count());
    }

    public function test_nothing_is_earned_while_the_feature_is_off(): void
    {
        $this->setUpShop();

        $this->assertNull($this->loyalty()->earnFor($this->order()));
        $this->assertSame(0, LoyaltyEntry::count());
    }

    public function test_order_paid_is_what_writes_the_entry(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        OrderPaid::dispatch($this->order());

        $this->assertSame(10, (int) LoyaltyEntry::where('type', LoyaltyEntry::EARN)->sum('points'));
    }

    // ------------------------------------------------------------- thu hồi

    public function test_a_returned_order_has_its_points_revoked_while_still_on_hold(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $order = $this->order();
        $this->loyalty()->earnFor($order);

        $returned = $this->moveOrderTo($order, OrderStatus::REFUNDED);
        OrderStatusUpdated::dispatch($returned, OrderStatus::PAYMENT_RECEIVED);

        // Sổ vẫn còn cả hai dòng — cộng rồi trừ, đối soát được. Số dư về 0 và
        // phần đang chờ cũng về 0: lô bị đóng khi còn nguyên.
        $this->assertSame(2, LoyaltyEntry::count());
        $this->assertSame(0, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(0, $this->loyalty()->pendingFor($this->customer));

        $this->travel(30)->days();
        $this->assertSame(0, $this->loyalty()->balanceFor($this->customer));
    }

    public function test_revoking_only_takes_what_is_left_of_the_lot(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $earned = $this->order();
        $lot = $this->loyalty()->earnFor($earned);

        $this->travel(15)->days();

        // Khách đã tiêu mất 4 điểm trước khi đơn kia bị trả lại.
        $this->loyalty()->adjust($this->customer, -4, 'test');
        $this->assertSame(6, $this->loyalty()->balanceFor($this->customer));

        $this->loyalty()->revokeForOrder($earned->refresh());

        // Thu hồi 6, không phải 10: kéo số dư xuống âm vì một đơn cũ là phạt
        // nhầm người.
        $this->assertSame(0, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(0, $this->loyalty()->lotRemaining($lot->refresh()));
    }

    // -------------------------------------------------------------- hết hạn

    public function test_expiry_is_a_ledger_entry_not_a_query_filter(): void
    {
        $this->setUpShop();
        $this->enableLoyalty(['hold_days' => 0, 'expire_days' => 30]);

        $this->loyalty()->earnFor($this->order());
        $this->assertSame(10, $this->loyalty()->balanceFor($this->customer));

        $this->travel(31)->days();

        $this->assertSame(10, $this->loyalty()->expireLots());

        // Sổ cộng lại vẫn ra số dư — không có điều kiện ẩn nào trong câu truy
        // vấn, nên câu hỏi "tháng trước khách mất bao nhiêu điểm" trả lời được.
        $this->assertSame(0, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(
            -10,
            (int) LoyaltyEntry::where('type', LoyaltyEntry::EXPIRE)->sum('points'),
        );
    }

    public function test_the_expiry_sweep_is_safe_to_run_twice(): void
    {
        $this->setUpShop();
        $this->enableLoyalty(['hold_days' => 0, 'expire_days' => 30]);

        $this->loyalty()->earnFor($this->order());
        $this->travel(31)->days();

        $this->assertSame(10, $this->loyalty()->expireLots());
        $this->assertSame(0, $this->loyalty()->expireLots());
        $this->assertSame(1, LoyaltyEntry::where('type', LoyaltyEntry::EXPIRE)->count());
    }

    public function test_the_command_does_nothing_while_the_feature_is_off(): void
    {
        $this->setUpShop();
        $this->enableLoyalty(['hold_days' => 0, 'expire_days' => 30]);
        $this->loyalty()->earnFor($this->order());
        $this->travel(31)->days();

        app(Settings::class)->put('loyalty', ['enabled' => false]);

        $this->artisan('loyalty:expire')->assertSuccessful();
        $this->assertSame(0, LoyaltyEntry::where('type', LoyaltyEntry::EXPIRE)->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->setUpShop();
        $this->enableLoyalty(['hold_days' => 0, 'expire_days' => 30]);
        $this->loyalty()->earnFor($this->order());
        $this->travel(31)->days();

        $this->artisan('loyalty:expire --dry-run')->assertSuccessful();

        $this->assertSame(0, LoyaltyEntry::where('type', LoyaltyEntry::EXPIRE)->count());
    }

    public function test_spending_eats_the_soonest_expiring_lot_first(): void
    {
        $this->setUpShop();
        $this->enableLoyalty(['hold_days' => 0, 'expire_days' => 0]);

        // Lô KHÔNG hết hạn được tạo TRƯỚC, lô sắp hết hạn tạo SAU. Thứ tự đó cố
        // ý ngược với thứ tự id: nếu ai đó bỏ sắp xếp theo hạn thì phần rơi lại
        // là `orderBy('id')`, và một fixture tạo lô ngắn hạn trước sẽ vẫn xanh
        // vì hai thứ tự trùng nhau.
        $long = $this->loyalty()->adjust($this->customer, 10, 'long');
        $short = $this->loyalty()->adjust($this->customer, 10, 'short');
        $short->update(['expires_at' => now()->addDays(5)]);

        $this->loyalty()->adjust($this->customer, -6, 'spend');

        // FIFO theo HẠN, không theo thứ tự nhận: lô sắp chết bị ăn trước, nên
        // điểm của khách không chết oan trong khi lô kia còn nguyên.
        $this->assertSame(4, $this->loyalty()->lotRemaining($short->refresh()));
        $this->assertSame(10, $this->loyalty()->lotRemaining($long->refresh()));
    }

    // ---------------------------------------------------------------- tiêu

    /** Một giỏ có hàng, gắn với khách đang xét, và có điểm tiêu được. */
    private function cartWithPoints(int $points = 100): void
    {
        $this->actingAs($this->shopper());

        $this->postJson('/api/v1/cart', [
            'variant_id' => $this->product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $lot = $this->loyalty()->adjust($this->customer, $points, 'test fixture');
        $lot->update(['available_at' => now()->subMinute()]);
    }

    public function test_redeeming_points_comes_off_the_total_after_tax(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints();

        $before = $this->getJson('/api/v1/cart')->assertSuccessful()->json('data.totals');

        // 50 điểm × 200₫ = 10.000₫ = 1.000.000 đơn vị nhỏ.
        $after = $this->postJson('/api/v1/cart/loyalty', ['points' => 50])
            ->assertOk()
            ->assertJsonPath('data.loyalty.applied', 50)
            ->json('data');

        $this->assertSame(
            $before['sub_total'],
            $after['totals']['sub_total'],
            'điểm là hình thức THANH TOÁN — tạm tính không được đổi',
        );
        $this->assertSame(
            $before['discount_total'],
            $after['totals']['discount_total'],
            'điểm không được trộn vào phần "bạn đã tiết kiệm"',
        );
        $this->assertNotSame($before['total'], $after['totals']['total']);
    }

    public function test_the_redeemed_total_survives_into_the_order_and_leaves_the_ledger(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints();

        $this->postJson('/api/v1/cart/loyalty', ['points' => 50])->assertOk();

        // Chọn tiêu điểm trên GIỎ không đụng vào sổ cái: giỏ mới chỉ là ý định,
        // và một giỏ bỏ quên không được phép làm bốc hơi điểm của khách. Sổ chỉ
        // chuyển khi đã có đơn.
        $this->assertSame(100, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(0, LoyaltyEntry::where('type', LoyaltyEntry::SPEND)->count());

        $this->postJson('/api/v1/checkout/addresses', ['shipping' => $this->shippingPayload()])->assertSuccessful();
        $this->postJson('/api/v1/checkout/shipping', ['identifier' => 'standard'])->assertSuccessful();
        $this->postJson('/api/v1/checkout', ['payment_type' => 'cod'])->assertSuccessful();

        $order = Order::latest('id')->firstOrFail();

        $this->assertSame(50, (int) data_get($order->meta, LoyaltyService::META_KEY));
        $this->assertSame(
            -50,
            (int) LoyaltyEntry::where('order_id', $order->id)->where('type', LoyaltyEntry::SPEND)->sum('points'),
        );

        // 100 − 50: số dư là SUM của sổ, không phải một cột được ghi đè.
        $this->assertSame(50, $this->loyalty()->balanceFor($this->customer));
    }

    public function test_spending_more_than_the_cap_is_refused_with_a_reason(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints(10000);

        // Trần 50% của một giỏ 100.000₫ là 50.000₫ = 250 điểm.
        $this->postJson('/api/v1/cart/loyalty', ['points' => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('points');

        $this->assertSame(0, $this->loyalty()->redemptionFor(app(CartService::class)->current()));
    }

    public function test_spending_below_the_minimum_is_refused(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints();

        $this->postJson('/api/v1/cart/loyalty', ['points' => 3])
            ->assertStatus(422)
            ->assertJsonValidationErrors('points');
    }

    public function test_a_guest_cannot_spend_points(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $this->postJson('/api/v1/cart', [
            'variant_id' => $this->product->variants->first()->id,
            'quantity' => 1,
        ])->assertSuccessful();

        $this->postJson('/api/v1/cart/loyalty', ['points' => 50])
            ->assertStatus(422)
            ->assertJsonValidationErrors('points');
    }

    public function test_removing_the_redemption_puts_the_total_back(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints();

        $before = $this->getJson('/api/v1/cart')->assertSuccessful()->json('data.totals.total');

        $this->postJson('/api/v1/cart/loyalty', ['points' => 50])->assertOk();
        $after = $this->deleteJson('/api/v1/cart/loyalty')->assertOk()->json('data');

        $this->assertSame($before, $after['totals']['total']);
        $this->assertSame(0, $after['loyalty']['applied']);
    }

    public function test_a_balance_that_drops_away_clamps_the_cart_instead_of_overcharging_points(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();
        $this->cartWithPoints();

        $this->postJson('/api/v1/cart/loyalty', ['points' => 50])->assertOk();

        // Điểm biến mất sau lúc chọn (hết hạn, staff trừ tay, đơn khác tiêu mất):
        // 100 − 60 = 40, ít hơn 50 điểm đang chọn.
        $this->loyalty()->adjust($this->customer, -60, 'test');

        // Giỏ tính lại thì kẹp xuống 40, không trừ tiền cho số điểm không còn.
        $this->getJson('/api/v1/cart')
            ->assertSuccessful()
            ->assertJsonPath('data.loyalty.applied', 40);
    }

    // ------------------------------------------------------- huỷ / hoàn tiền

    public function test_cancelling_an_order_gives_the_spent_points_back(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $lot = $this->loyalty()->adjust($this->customer, 100, 'fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        $order = $this->order(meta: [LoyaltyService::META_KEY => 50]);
        $this->loyalty()->commitRedemption($order);

        $this->assertSame(50, $this->loyalty()->balanceFor($this->customer));

        $cancelled = $this->moveOrderTo($order, OrderStatus::CANCELLED);
        OrderStatusUpdated::dispatch($cancelled, OrderStatus::PAYMENT_RECEIVED);

        $this->assertSame(100, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(
            50,
            (int) LoyaltyEntry::where('type', LoyaltyEntry::REFUND)->sum('points'),
        );
    }

    public function test_points_are_given_back_only_once(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $lot = $this->loyalty()->adjust($this->customer, 100, 'fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        $order = $this->order(meta: [LoyaltyService::META_KEY => 50]);
        $this->loyalty()->commitRedemption($order);

        $this->loyalty()->refundRedemption($order);
        $this->loyalty()->refundRedemption($order);

        $this->assertSame(1, LoyaltyEntry::where('type', LoyaltyEntry::REFUND)->count());
        $this->assertSame(100, $this->loyalty()->balanceFor($this->customer));
    }

    public function test_an_order_that_both_earned_and_spent_unwinds_both_sides(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $lot = $this->loyalty()->adjust($this->customer, 100, 'fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        $order = $this->order(meta: [LoyaltyService::META_KEY => 50]);
        $this->loyalty()->commitRedemption($order);
        $this->loyalty()->earnFor($order);

        // Tiêu 50 (còn 50 khả dụng), cộng 10 đang chờ.
        $this->assertSame(50, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(10, $this->loyalty()->pendingFor($this->customer));

        $refunded = $this->moveOrderTo($order, OrderStatus::REFUNDED);
        OrderStatusUpdated::dispatch($refunded, OrderStatus::PAYMENT_RECEIVED);

        // Trả lại 50 đã tiêu, thu hồi 10 đã thưởng — hai chiều ngược nhau và
        // cả hai đều phải xảy ra.
        $this->assertSame(100, $this->loyalty()->balanceFor($this->customer));
        $this->assertSame(0, $this->loyalty()->pendingFor($this->customer));
    }

    // ------------------------------------------------------------- hiển thị

    public function test_the_account_page_shows_the_balance_without_javascript(): void
    {
        $this->setUpShop();
        $this->enableLoyalty();

        $lot = $this->loyalty()->adjust($this->customer, 250, 'fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        $this->actingAs($this->shopper())
            ->get('/account')
            ->assertOk()
            ->assertSee(__('storefront.loyalty.title'))
            ->assertSee('250');
    }

    public function test_the_account_page_says_nothing_while_the_feature_is_off(): void
    {
        $this->setUpShop();

        $this->actingAs($this->shopper())
            ->get('/account')
            ->assertOk()
            ->assertDontSee(__('storefront.loyalty.title'));
    }
}
