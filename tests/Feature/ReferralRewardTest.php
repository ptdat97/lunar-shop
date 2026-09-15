<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Discount;
use Lunar\Core\Models\Order;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Models\ReturnRequest;
use Modules\Order\Support\OrderStatus;
use Modules\Promotion\Models\ReferralClaim;
use Modules\Promotion\Services\ReferralService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\Concerns\DrivesOrderLifecycle;
use Tests\TestCase;

/**
 * Thưởng cho NGƯỜI MỜI — và toàn bộ lý do nó không được phát ngay.
 *
 * Luật của shop: thưởng chỉ được trả sau khi đơn của người được mời đã qua thời
 * gian đổi/trả. Vì thế có hai nửa, và cả hai đều được ghim ở đây:
 *
 *   OrderPaid          chỉ ĐÁNH DẤU lượt giới thiệu là đang chờ
 *   referrals:release  mới PHÁT thưởng, và chỉ khi đơn không bị trả lại
 *
 * Bỏ nửa thứ hai đi thì khách trả hàng xong vẫn giữ coupon thưởng — đó là lỗi
 * tốn tiền thật, không phải chuyện hình thức.
 */
class ReferralRewardTest extends TestCase
{
    use CreatesStorefrontData;
    use DrivesOrderLifecycle;

    private function enable(int $delayDays = 14): void
    {
        config([
            'referral.enabled' => true,
            'referral.welcome_percentage' => 10,
            'referral.welcome_valid_days' => 30,
            'referral.reward_percentage' => 10,
            'referral.reward_valid_days' => 60,
            'referral.reward_delay_days' => $delayDays,
        ]);
    }

    /** Một lượt giới thiệu thật: người mời có mã, bạn của họ đăng ký bằng mã đó. */
    private function claim(int $delayDays = 14): ReferralClaim
    {
        $this->enable($delayDays);

        $referrer = $this->createUser(['name' => 'Referrer One']);
        $referrerCustomer = app(CustomerResolver::class)->forUser($referrer);
        $code = app(ReferralService::class)->codeFor($referrerCustomer)->code;

        $friend = $this->createUser(['name' => 'Friend Two']);

        return app(ReferralService::class)->claim($friend, $code);
    }

    /**
     * Đơn ĐẦU TIÊN của người được mời, dựng theo đúng cách DrivesOrderLifecycle
     * dựng (cột nào, payment_type nào) để status suy ra được như thật.
     */
    private function orderFor(ReferralClaim $claim, string $status = OrderStatus::PAYMENT_OFFLINE): Order
    {
        return Order::factory()->create([
            'customer_id' => $claim->referred_customer_id,
            'channel_id' => Channel::getDefault()->id,
            'currency_code' => Currency::getDefault()->code,
            ...$this->orderAttributesFor($status),
            'sub_total' => 100_000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'total' => 100_000,
        ]);
    }

    /** Đơn đã trả tiền lâu rồi — tức đã qua hạn đổi/trả. */
    private function backdate(ReferralClaim $claim, int $days = 15): ReferralClaim
    {
        $claim->forceFill(['qualified_at' => now()->subDays($days)])->save();

        return $claim->refresh();
    }

    public function test_a_paid_order_starts_the_waiting_period(): void
    {
        $claim = $this->claim();
        $order = $this->orderFor($claim);

        OrderPaid::dispatch($order);

        $claim->refresh();

        $this->assertSame(ReferralClaim::AWAITING, $claim->status);
        $this->assertSame($order->id, $claim->order_id);
        $this->assertNotNull($claim->qualified_at);
        $this->assertNull($claim->reward_discount_id);
    }

    public function test_the_reward_waits_for_the_return_window(): void
    {
        $claim = $this->claim(delayDays: 14);
        OrderPaid::dispatch($this->orderFor($claim));

        // Đơn vừa được trả tiền: còn trong hạn đổi/trả.
        Artisan::call('referrals:release');

        $this->assertSame(ReferralClaim::AWAITING, $claim->refresh()->status);
        $this->assertNull($claim->reward_discount_id);

        // Giờ coi như đơn đã trả tiền 15 ngày trước.
        $this->backdate($claim, 15);
        Artisan::call('referrals:release');

        $claim->refresh();

        $this->assertSame(ReferralClaim::REWARDED, $claim->status);
        $this->assertNotNull($claim->rewarded_at);

        $coupon = Discount::findOrFail($claim->reward_discount_id);

        $this->assertStringStartsWith('REWARD-', $coupon->coupon);
        $this->assertSame(10, (int) $coupon->data['percentage']);
        $this->assertSame(1, $coupon->max_uses);
        $this->assertTrue($coupon->ends_at->isFuture());
    }

    /** Hàng đã về thì không có thưởng — đây là cả lý do có khoảng chờ. */
    public function test_a_returned_order_is_never_rewarded(): void
    {
        $claim = $this->claim();
        $order = $this->orderFor($claim);
        OrderPaid::dispatch($order);

        ReturnRequest::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'reference' => 'RT-REF-1',
            'status' => ReturnRequest::REQUESTED,
            'reason' => 'too-small',
        ]);

        $this->backdate($claim);
        Artisan::call('referrals:release');

        $claim->refresh();

        $this->assertSame(ReferralClaim::VOIDED, $claim->status);
        $this->assertSame(ReferralService::VOID_RETURNED, $claim->voided_reason);
        $this->assertNull($claim->reward_discount_id);
    }

    /** Tiền đã trả lại cũng vậy — nửa còn lại của cùng một luật. */
    public function test_a_refunded_order_is_never_rewarded(): void
    {
        $claim = $this->claim();
        $order = $this->orderFor($claim, OrderStatus::PAYMENT_RECEIVED);
        OrderPaid::dispatch($order);

        $this->moveOrderTo($order->fresh(), OrderStatus::REFUNDED);
        $this->backdate($claim);
        Artisan::call('referrals:release');

        $claim->refresh();

        $this->assertSame(ReferralClaim::VOIDED, $claim->status);
        $this->assertNull($claim->reward_discount_id);
    }

    /**
     * Chỉ đơn ĐẦU TIÊN tính. Nếu không, một lượt giới thiệu có thể được "làm
     * mới" bằng đơn thứ hai sau khi đơn đầu bị trả lại — đúng cách kiếm coupon.
     */
    public function test_only_the_first_paid_order_counts(): void
    {
        $claim = $this->claim();

        $first = $this->orderFor($claim);
        OrderPaid::dispatch($first);

        $second = $this->orderFor($claim);
        OrderPaid::dispatch($second);

        $this->assertSame($first->id, $claim->refresh()->order_id);
    }

    public function test_a_reward_is_only_issued_once(): void
    {
        $claim = $this->claim();
        OrderPaid::dispatch($this->orderFor($claim));
        $this->backdate($claim);

        Artisan::call('referrals:release');
        $couponId = $claim->refresh()->reward_discount_id;

        Artisan::call('referrals:release');

        $this->assertNotNull($couponId);
        $this->assertSame($couponId, $claim->refresh()->reward_discount_id);
        $this->assertSame(1, Discount::query()->where('coupon', 'like', 'REWARD-%')->count());
    }

    public function test_a_dry_run_issues_nothing(): void
    {
        $claim = $this->claim();
        OrderPaid::dispatch($this->orderFor($claim));
        $this->backdate($claim);

        Artisan::call('referrals:release', ['--dry-run' => true]);

        $claim->refresh();

        $this->assertSame(ReferralClaim::AWAITING, $claim->status);
        $this->assertNull($claim->reward_discount_id);
        $this->assertSame(0, Discount::query()->where('coupon', 'like', 'REWARD-%')->count());
    }

    /**
     * Tắt tính năng không có nghĩa là đóng hàng đợi: lượt cũ vẫn nằm đó và vẫn
     * được xét khi bật lại. Lệnh chỉ báo là nó không chạy, không phá dữ liệu.
     */
    public function test_nothing_is_rewarded_while_the_feature_is_off(): void
    {
        $claim = $this->claim();
        OrderPaid::dispatch($this->orderFor($claim));
        $this->backdate($claim, 30);

        config(['referral.enabled' => false]);
        Artisan::call('referrals:release');

        $this->assertSame(ReferralClaim::AWAITING, $claim->refresh()->status);
        $this->assertNull($claim->reward_discount_id);
        $this->assertStringContainsString('TẮT', Artisan::output());
    }

    public function test_the_release_option_overrides_the_configured_wait(): void
    {
        $claim = $this->claim(delayDays: 60);
        OrderPaid::dispatch($this->orderFor($claim));
        $this->backdate($claim, 15);

        // 15 ngày chưa đủ với ngưỡng 60 của shop...
        Artisan::call('referrals:release');
        $this->assertSame(ReferralClaim::AWAITING, $claim->refresh()->status);

        // ...nhưng đủ với ngưỡng ghi đè, dùng khi cần phát tay một đợt.
        Artisan::call('referrals:release', ['--days' => 14]);
        $this->assertSame(ReferralClaim::REWARDED, $claim->refresh()->status);
    }
}
