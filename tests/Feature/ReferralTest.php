<?php

namespace Tests\Feature;

use App\Models\User;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Discount;
use Modules\Customer\Services\CustomerResolver;
use Modules\Promotion\Models\ReferralClaim;
use Modules\Promotion\Services\ReferralService;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Giới thiệu bạn: mã mời, ghi nhận người được mời lúc đăng ký, coupon chào mừng.
 *
 * Phần thưởng cho NGƯỜI MỜI nằm ở ReferralRewardTest — nó có vòng đời riêng
 * (chờ hết hạn đổi/trả) và một cái bẫy riêng (đơn trả lại vẫn ăn thưởng).
 *
 * Ba thứ được ghim ở đây là ba cách tính năng này mất tiền oan:
 *
 *   - mã bị dùng lại (mỗi mã chỉ tiêu một lần, kể cả khi lượt đó bị từ chối)
 *   - tự giới thiệu (cùng user, hoặc cùng thiết bị với người mời)
 *   - một người được giới thiệu hai lần (hai coupon chào mừng cho một người)
 */
class ReferralTest extends TestCase
{
    use CreatesStorefrontData;

    /** @param array<string, mixed> $overrides */
    private function enable(array $overrides = []): void
    {
        config([
            'referral.enabled' => true,
            'referral.welcome_percentage' => 10,
            'referral.welcome_valid_days' => 30,
            'referral.reward_percentage' => 10,
            'referral.reward_valid_days' => 60,
            'referral.reward_delay_days' => 14,
        ]);

        if ($overrides !== []) {
            config($overrides);
        }
    }

    /**
     * A customer with a login — i.e. someone who can actually hand out a code.
     *
     * @return array{0: User, 1: Customer}
     */
    private function customer(string $name = 'Referrer One'): array
    {
        $user = $this->createUser(['name' => $name]);

        return [$user, app(CustomerResolver::class)->forUser($user)];
    }

    private function inviteCode(string $name = 'Referrer One'): string
    {
        [, $customer] = $this->customer($name);

        return app(ReferralService::class)->codeFor($customer)->code;
    }

    /** Register through the public endpoint — the path a friend actually takes. */
    private function register(string $name = 'Friend Two'): User
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'-'.uniqid().'@example.com',
            'password' => 'password123',
        ])->assertCreated();

        return User::query()->latest('id')->firstOrFail();
    }

    public function test_the_account_page_renders_the_invite_link(): void
    {
        $this->enable();
        [$user, $customer] = $this->customer();

        $response = $this->actingAs($user)->get('/account');

        $response->assertOk();

        $code = app(ReferralService::class)->codeFor($customer)->code;
        $response->assertSee($code);
        $response->assertSee('/r/'.$code, escape: false);
    }

    public function test_visiting_an_invite_link_remembers_the_code(): void
    {
        $this->enable();
        $code = $this->inviteCode();

        $this->get('/r/'.$code)->assertRedirect(route('storefront.home'));

        $this->assertSame($code, session(ReferralService::SESSION_KEY));
    }

    public function test_an_unknown_code_is_ignored(): void
    {
        $this->enable();

        $this->get('/r/NOPE1234')->assertRedirect(route('storefront.home'));

        $this->assertNull(session(ReferralService::SESSION_KEY));
    }

    /**
     * A referral link is shared far and wide, so it must not become an open
     * redirect: `?to=` takes a local path or nothing at all.
     */
    public function test_the_invite_link_only_redirects_to_local_paths(): void
    {
        $this->enable();
        $code = $this->inviteCode();

        $this->get('/r/'.$code.'?to=https://evil.example')->assertRedirect(route('storefront.home'));
        $this->get('/r/'.$code.'?to=//evil.example')->assertRedirect(route('storefront.home'));
        $this->get('/r/'.$code.'?to=/cart')->assertRedirect('/cart');
    }

    public function test_registering_through_a_link_claims_it_and_issues_a_welcome_coupon(): void
    {
        $this->enable();
        $code = $this->inviteCode();

        $this->get('/r/'.$code);
        $friend = $this->register();
        $friendCustomer = app(CustomerResolver::class)->forUser($friend);

        $claim = ReferralClaim::query()->sole();

        $this->assertSame($code, $claim->code->code);
        $this->assertSame($friendCustomer->id, $claim->referred_customer_id);
        $this->assertSame($friend->id, $claim->referred_user_id);
        $this->assertSame(ReferralClaim::CLAIMED, $claim->status);

        // Coupon là coupon Lunar thật: một lần dùng, có hạn, đi đúng đường mà
        // mọi mã giảm giá khác của shop đi.
        $coupon = Discount::findOrFail($claim->welcome_discount_id);

        $this->assertStringStartsWith('WELCOME-', $coupon->coupon);
        $this->assertSame(10, (int) $coupon->data['percentage']);
        $this->assertSame(1, $coupon->max_uses);
        $this->assertSame(1, $coupon->max_uses_per_user);
        $this->assertTrue($coupon->ends_at->isFuture());
    }

    public function test_the_welcome_coupon_shows_on_the_friends_account_page(): void
    {
        $this->enable();
        $code = $this->inviteCode();

        $this->get('/r/'.$code);
        $friend = $this->register();

        $coupon = Discount::findOrFail(ReferralClaim::query()->sole()->welcome_discount_id);

        $this->actingAs($friend)->get('/account')
            ->assertOk()
            ->assertSee($coupon->coupon);
    }

    public function test_a_customer_cannot_refer_themselves(): void
    {
        $this->enable();
        [$user, $customer] = $this->customer();

        $code = app(ReferralService::class)->codeFor($customer)->code;

        $this->assertNull(app(ReferralService::class)->claim($user, $code));
        $this->assertSame(0, ReferralClaim::query()->count());
    }

    /**
     * A second account on the same device is a different `user`, so the user_id
     * check cannot see it — the device fingerprint is what does.
     */
    public function test_a_claim_from_the_referrers_own_device_is_refused(): void
    {
        $this->enable();
        [, $customer] = $this->customer();

        $code = app(ReferralService::class)->codeFor($customer, 'same-device')->code;
        $friend = $this->createUser(['name' => 'Friend Two']);

        $this->assertNull(app(ReferralService::class)->claim($friend, $code, 'same-device'));
        $this->assertSame(0, ReferralClaim::query()->count());
    }

    public function test_a_customer_can_only_be_referred_once(): void
    {
        $this->enable();
        $first = $this->inviteCode('Referrer One');
        $second = $this->inviteCode('Referrer Two');

        $friend = $this->createUser(['name' => 'Friend Two']);
        $referrals = app(ReferralService::class);

        $this->assertNotNull($referrals->claim($friend, $first));
        $this->assertNull($referrals->claim($friend, $second));
        $this->assertSame(1, ReferralClaim::query()->count());
    }

    /**
     * The code is spent by the ATTEMPT, not by the outcome: leaving it in the
     * session would hand it to whoever signs up next on the same device.
     */
    public function test_a_refused_code_is_not_kept_for_the_next_signup(): void
    {
        $this->enable();
        [$user, $customer] = $this->customer();
        $code = app(ReferralService::class)->codeFor($customer)->code;

        $this->get('/r/'.$code);
        $this->assertNull(app(ReferralService::class)->claim($user, $code));

        $this->assertNull(session(ReferralService::SESSION_KEY));
    }

    public function test_nothing_happens_while_the_feature_is_off(): void
    {
        // Không gọi enable(): config mặc định của shop là TẮT.
        [$user, $customer] = $this->customer();
        $code = app(ReferralService::class)->codeFor($customer)->code;

        $this->get('/r/'.$code);
        $this->assertNull(session(ReferralService::SESSION_KEY));

        $friend = $this->createUser(['name' => 'Friend Two']);
        $this->assertNull(app(ReferralService::class)->claim($friend, $code));
        $this->assertSame(0, ReferralClaim::query()->count());

        $this->actingAs($user)->get('/account')->assertDontSee('data-referral');
    }
}
