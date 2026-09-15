<?php

namespace Modules\Promotion\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lunar\Core\DiscountTypes\PercentageOff;
use Lunar\Core\Models\Channel;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Discount;
use Lunar\Core\Models\Order;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Order\Support\OrderStatus;
use Modules\Promotion\Models\ReferralClaim;
use Modules\Promotion\Models\ReferralCode;

/**
 * Giới thiệu bạn — mã mời, ghi nhận người được mời, và tiền thưởng cho người mời.
 *
 * SME thời trang lớn lên bằng truyền miệng, và kênh thu khách rẻ nhất là khách
 * cũ dắt bạn bè. Hạ tầng mã giảm giá đã có (Lunar discounts + coupon), nên ở
 * đây chỉ có phần mà Lunar không biết: mã của ai, ai đã dùng nó, và **thưởng
 * chỉ được trả khi đơn đã qua thời gian đổi/trả**.
 *
 * Hai quyết định đáng ghi lại:
 *
 * 1. Ghi nhận người được mời xảy ra lúc ĐĂNG KÝ, không phải lúc đặt hàng. Cần
 *    một tài khoản để chống trại mã (user_id + thiết bị), và tài khoản là thứ
 *    duy nhất phân biệt "một người" với "một lượt khách vãng lai". Cái giá:
 *    khách mua không đăng ký thì không có thưởng — cố ý.
 *
 * 2. Coupon phát ra là coupon Lunar THẬT, một lần dùng (`max_uses` và
 *    `max_uses_per_user` = 1) và có hạn. Không giới hạn theo từng khách dù
 *    Lunar có `customers` cho việc đó: discount scoped-customer chỉ áp được khi
 *    **cart đã có customer**, mà cart tạo lúc còn là khách vãng lai thì tới tận
 *    checkout mới được gán (CheckoutService) — hệ quả là "nhập mã ở giỏ không
 *    thấy gì, tới checkout mới thấy giảm", đúng loại lỗi im lặng tệ hơn cả việc
 *    mã bị chia sẻ, vốn đã bị chặn bởi một-lần-dùng + hạn.
 */
class ReferralService
{
    /** Nơi giữ mã đã bắt được, cho tới khi khách đăng ký. */
    public const SESSION_KEY = 'referral.code';

    /** Chờ bao lâu sau khi đơn được thanh toán — "hết hạn đổi/trả" của shop. */
    public const DEFAULT_REWARD_DELAY_DAYS = 14;

    public const MAX_REWARD_DELAY_DAYS = 120;

    /** Trần cho phần trăm giảm giá phát ra từ đây. */
    public const MAX_PERCENTAGE = 100;

    /** Bao nhiêu bạn bè gần nhất hiện trên trang tài khoản. */
    public const ACCOUNT_LIST_LIMIT = 20;

    /** Lý do đóng một claim mà không thưởng. */
    public const VOID_RETURNED = 'returned';

    public const VOID_NO_REFERRER = 'referrer-missing';

    public const VOID_MANUAL = 'manual';

    public function __construct(
        protected Settings $settings,
        protected CustomerResolver $customers,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('referral.enabled', false);
    }

    public function welcomePercentage(): int
    {
        return $this->percentage('referral.welcome_percentage', 10);
    }

    public function welcomeValidDays(): int
    {
        return $this->days('referral.welcome_valid_days', 30);
    }

    public function rewardPercentage(): int
    {
        return $this->percentage('referral.reward_percentage', 10);
    }

    public function rewardValidDays(): int
    {
        return $this->days('referral.reward_valid_days', 60);
    }

    /**
     * Ngưỡng chờ trước khi phát thưởng, tính bằng ngày.
     *
     * Kẹp trong khoảng hợp lệ chứ không tin dữ liệu đã lưu: một số âm ở đây
     * nghĩa là thưởng được phát ngay khi đơn vừa trả tiền — đúng thứ mà cơ chế
     * này tồn tại để tránh.
     */
    public function rewardDelayDays(): int
    {
        $days = (int) $this->settings->get('referral.reward_delay_days', self::DEFAULT_REWARD_DELAY_DAYS);

        return max(0, min(self::MAX_REWARD_DELAY_DAYS, $days));
    }

    /**
     * Mã của khách, tạo khi lần đầu cần.
     *
     * `$fingerprint` là dấu vân tay thiết bị của chính người mời lúc họ mở trang
     * giới thiệu — ghi một lần, không ghi đè. Nhờ nó mà lượt đăng ký từ chính
     * thiết bị đó bị từ chối ({@see self::claim()}): `user_id` không nhìn ra
     * chuyện này, vì tài khoản thứ hai là một `user` khác.
     */
    public function codeFor(Customer $customer, ?string $fingerprint = null): ReferralCode
    {
        $code = ReferralCode::firstOrCreate(
            ['customer_id' => $customer->id],
            ['code' => $this->uniqueCode()],
        );

        if ($fingerprint !== null && $code->fingerprint === null) {
            $code->update(['fingerprint' => $fingerprint]);
        }

        return $code;
    }

    /** Tra mã đã chia sẻ. Không phân biệt hoa/thường; bỏ khoảng trắng thừa. */
    public function findCode(?string $code): ?ReferralCode
    {
        $code = $this->normaliseCode($code);

        return $code === '' ? null : ReferralCode::query()->where('code', $code)->first();
    }

    /**
     * Bắt mã từ link mời (`/r/{code}`) và giữ trong session tới lúc đăng ký.
     *
     * Trả về true/false để chỗ gọi biết có gì đó đã bắt được, KHÔNG ném lỗi:
     * một link cũ hay sai phải đưa khách về trang chủ bình thường, không phải
     * một trang lỗi.
     */
    public function capture(string $code): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $invite = $this->findCode($code);

        if (! $invite) {
            return false;
        }

        session([self::SESSION_KEY => $invite->code]);

        return true;
    }

    /** Mã đang chờ, nếu còn. */
    public function pendingCode(): ?string
    {
        $code = session(self::SESSION_KEY);

        return is_string($code) && $code !== '' ? $code : null;
    }

    public function forgetPendingCode(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * Ghi nhận người được mời khi họ vừa đăng ký.
     *
     * Trả về null khi không có gì để ghi — tính năng tắt, không có mã, mã không
     * tồn tại, hoặc là tự giới thiệu. Không ném lỗi: việc đăng ký phải thành
     * công bất kể chuyện giới thiệu ra sao. Mọi lượt bị từ chối đều đi qua log,
     * vì "khách bảo có mã mà hệ thống không thấy" là câu hỏi chỉ log trả lời được.
     */
    public function claim(User $user, ?string $code = null, ?string $fingerprint = null): ?ReferralClaim
    {
        $code = $this->normaliseCode($code ?? $this->pendingCode());

        // Mã chỉ được tiêu một lần, bất kể kết quả: để nó lại trong session thì
        // lượt đăng ký sau trên cùng máy (một tài khoản khác) lại dùng lại nó.
        if ($code !== '') {
            $this->forgetPendingCode();
        }

        if (! $this->enabled() || $code === '') {
            return null;
        }

        $invite = $this->findCode($code);
        $referrer = $invite?->customer;

        if (! $invite || ! $referrer) {
            Log::info('Referral refused: unknown code', ['code' => $code]);

            return null;
        }

        $customer = $this->customers->forUser($user);

        // Tự giới thiệu: cùng khách (không thể, vì mỗi user một tài khoản) hoặc
        // cùng USER — trường hợp thật, khi khách tạo tài khoản thứ hai rồi tự
        // bấm link của mình.
        if ($referrer->id === $customer->id || $referrer->users()->whereKey($user->id)->exists()) {
            Log::info('Referral refused: self-referral', [
                'code' => $code,
                'referrer_customer' => $referrer->id,
                'referred_customer' => $customer->id,
            ]);

            return null;
        }

        // Cùng thiết bị với người mời: `user_id` không thấy được điều này.
        if ($fingerprint !== null && $invite->fingerprint === $fingerprint) {
            Log::info('Referral refused: same device as the referrer', ['code' => $code]);

            return null;
        }

        // Một người chỉ được giới thiệu một lần. Cột `referred_customer_id` là
        // UNIQUE, nhưng kiểm trước để trả về null thay vì để DB ném lỗi.
        if (ReferralClaim::query()->where('referred_customer_id', $customer->id)->exists()) {
            return null;
        }

        $claim = ReferralClaim::create([
            'referral_code_id' => $invite->id,
            'referrer_customer_id' => $referrer->id,
            'referred_customer_id' => $customer->id,
            'referred_user_id' => $user->id,
            'status' => ReferralClaim::CLAIMED,
            'fingerprint' => $fingerprint,
            'claimed_at' => now(),
        ]);

        $percentage = $this->welcomePercentage();

        if ($percentage > 0) {
            $claim->update([
                'welcome_discount_id' => $this->issueCoupon(
                    prefix: 'WELCOME',
                    name: __('admin.referral.welcome_coupon'),
                    percentage: $percentage,
                    validDays: $this->welcomeValidDays(),
                )->id,
            ]);
        }

        return $claim->refresh();
    }

    /**
     * Phát một coupon Lunar dùng-một-lần.
     *
     * Đi đúng đường mà mọi mã giảm giá khác của shop đi: một dòng trong
     * `lunar_discounts` có `coupon`, gắn vào mọi channel. Không có bảng coupon
     * riêng, và không có logic tính tiền riêng — cái shop phải bảo trì vì thế
     * vẫn chỉ là một engine giảm giá.
     */
    protected function issueCoupon(string $prefix, string $name, int $percentage, int $validDays): Discount
    {
        $code = $this->uniqueCouponCode($prefix);

        $discount = Discount::create([
            'name' => $name,
            'handle' => Str::slug($code),
            'coupon' => $code,
            'type' => PercentageOff::class,
            'starts_at' => now(),
            'ends_at' => now()->addDays($validDays),
            'uses' => 0,
            // Một lần dùng. Coupon là bearer token — ai biết mã cũng nhập được —
            // nên "một lần" + "có hạn" là hai thứ duy nhất chặn thiệt hại khi
            // mã bị lộ ra ngoài người được tặng.
            'max_uses' => 1,
            'max_uses_per_user' => 1,
            'priority' => 5,
            'stop' => false,
            'data' => ['percentage' => max(1, min(self::MAX_PERCENTAGE, $percentage))],
        ]);

        $channels = Channel::all();

        if ($channels->isNotEmpty()) {
            // Một lần với CẢ danh sách: scheduleChannel() là sync(), nên gọi
            // trong vòng lặp sẽ để lại đúng channel cuối cùng.
            $discount->scheduleChannel($channels, now());
        }

        return $discount;
    }

    /**
     * Đơn đầu tiên của người được mời đã thanh toán → bắt đầu đếm ngược.
     *
     * Chỉ đơn ĐẦU TIÊN: khi claim đã ở awaiting/rewarded/voided thì đơn sau
     * không làm gì. Nếu đơn đầu bị trả lại thì claim bị void — không có chuyện
     * đơn thứ hai thay thế, vì như vậy là thưởng cho một người vừa trả hàng.
     */
    public function markQualifyingOrder(Order $order): void
    {
        if (! $this->enabled() || ! $order->customer_id) {
            return;
        }

        $claim = ReferralClaim::query()
            ->where('referred_customer_id', $order->customer_id)
            ->where('status', ReferralClaim::CLAIMED)
            ->first();

        if (! $claim) {
            return;
        }

        $claim->update([
            'status' => ReferralClaim::AWAITING,
            'order_id' => $order->id,
            'qualified_at' => now(),
        ]);
    }

    /**
     * Những claim đã chờ đủ lâu để xét thưởng.
     *
     * `reward_delay_days = 0` là hợp lệ (shop muốn thưởng ngay khi đơn được trả
     * tiền), nên điều kiện là `<=` now() — không có nhánh đặc biệt nào để một
     * ngày bằng 0 vô tình bị bỏ qua.
     *
     * @return Collection<int, ReferralClaim>
     */
    public function dueForReward(?int $days = null, int $limit = 200): Collection
    {
        $days ??= $this->rewardDelayDays();

        return ReferralClaim::query()
            ->where('status', ReferralClaim::AWAITING)
            ->whereNotNull('qualified_at')
            ->where('qualified_at', '<=', now()->subDays($days))
            ->with(['referrer', 'order'])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Xét một claim: phát thưởng, hoặc void nếu đơn đã về/đã hoàn tiền.
     *
     * @return string released|voided|skipped
     */
    public function settle(ReferralClaim $claim): string
    {
        if ($claim->status !== ReferralClaim::AWAITING) {
            return 'skipped';
        }

        $order = $claim->order;

        // Hàng đã về hoặc tiền đã trả lại thì không có thưởng. Đây chính là lý
        // do thưởng không được phát ngay lúc thanh toán.
        if (! $order || OrderStatus::wasReturnedOrRefunded($order)) {
            $this->void($claim, self::VOID_RETURNED);

            return 'voided';
        }

        $referrer = $claim->referrer;

        if (! $referrer) {
            // Khách bị xoá kể từ lúc được giới thiệu. Không có ai để thưởng thì
            // đóng lại, chứ không để nằm mãi trong danh sách chờ.
            $this->void($claim, self::VOID_NO_REFERRER);

            return 'voided';
        }

        $percentage = $this->rewardPercentage();

        if ($percentage <= 0) {
            return 'skipped';
        }

        $discount = $this->issueCoupon(
            prefix: 'REWARD',
            name: __('admin.referral.reward_coupon'),
            percentage: $percentage,
            validDays: $this->rewardValidDays(),
        );

        $claim->update([
            'status' => ReferralClaim::REWARDED,
            'reward_discount_id' => $discount->id,
            'rewarded_at' => now(),
        ]);

        return 'released';
    }

    /** Đóng một claim mà không thưởng. */
    public function void(ReferralClaim $claim, string $reason): ReferralClaim
    {
        $claim->update([
            'status' => ReferralClaim::VOIDED,
            'voided_reason' => $reason,
        ]);

        return $claim->refresh();
    }

    /**
     * Dữ liệu cho khối giới thiệu trên trang tài khoản (SSR).
     *
     * Đọc trước, ghi sau — và chỗ ghi duy nhất chính là đây: trang tài khoản là
     * nơi khối này hiện ra, nên cũng là nơi mã được tạo nếu khách chưa có, và
     * lượt xem đó ghi lại dấu vân tay thiết bị của người mời (cơ sở để chặn lượt
     * đăng ký từ cùng máy). Trả về null khi tính năng tắt, để Blade không phải
     * biết gì về cờ đó.
     *
     * @return array<string, mixed>|null
     */
    public function viewDataFor(User $user, ?Request $request = null): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $customer = $this->customers->forUser($user);
        $invite = $this->codeFor($customer, $request ? $this->fingerprintFor($request) : null);

        $claims = ReferralClaim::query()
            ->where('referrer_customer_id', $customer->id)
            ->with(['referred', 'order'])
            ->latest('id')
            ->limit(self::ACCOUNT_LIST_LIMIT)
            ->get();

        // Coupon nằm ở bảng của Lunar và claim chỉ giữ id, nên lấy một lượt cho
        // cả danh sách thay vì một truy vấn mỗi dòng.
        $discounts = Discount::query()
            ->whereIn('id', $claims->pluck('reward_discount_id')->filter()->all())
            ->get(['id', 'coupon'])
            ->keyBy('id');

        $own = ReferralClaim::query()->where('referred_customer_id', $customer->id)->first();

        return [
            'code' => $invite->code,
            'link' => route('storefront.referral.link', ['code' => $invite->code]),
            'welcome_percentage' => $this->welcomePercentage(),
            'reward_percentage' => $this->rewardPercentage(),
            'reward_delay_days' => $this->rewardDelayDays(),
            'invited' => $claims->count(),
            'awaiting' => $claims->where('status', ReferralClaim::AWAITING)->count(),
            'rewarded' => $claims->where('status', ReferralClaim::REWARDED)->count(),
            'friends' => $claims->map(fn (ReferralClaim $claim): array => [
                // Chỉ tên riêng: đây là bạn của khách này, nhưng họ của họ không
                // phải việc của khách kia.
                'name' => $claim->referred?->first_name ?: __('storefront.referral.friend'),
                'status' => $claim->status,
                'reward_code' => $discounts->get($claim->reward_discount_id)?->coupon,
                'date' => $claim->claimed_at?->translatedFormat('d/m/Y'),
            ])->all(),
            'welcome' => $this->welcomeCouponData($own),
        ];
    }

    /**
     * Coupon chào mừng của chính người đang xem, nếu họ là người được mời.
     *
     * @return array<string, mixed>|null
     */
    protected function welcomeCouponData(?ReferralClaim $own): ?array
    {
        $discount = $own?->welcome_discount_id ? Discount::find($own->welcome_discount_id) : null;

        if (! $discount) {
            return null;
        }

        return [
            'code' => $discount->coupon,
            'percentage' => (int) ($discount->data['percentage'] ?? 0),
            'expires_at' => $discount->ends_at?->translatedFormat('d/m/Y'),
            'used' => (int) $discount->uses > 0,
            'expired' => $discount->ends_at !== null && $discount->ends_at->isPast(),
        ];
    }

    /**
     * Dấu vân tay thiết bị — thô, nhưng đủ để chặn trại mã cùng một máy.
     *
     * Không lưu IP hay user agent thô: chúng là dữ liệu cá nhân, còn việc cần
     * làm chỉ là so hai chuỗi có bằng nhau hay không.
     */
    public function fingerprintFor(Request $request): string
    {
        return hash('sha256', (string) $request->userAgent().'|'.(string) $request->ip());
    }

    protected function normaliseCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    /** Mã mời: 8 ký tự, không dấu gạch — dễ đọc lại qua điện thoại. */
    protected function uniqueCode(): string
    {
        return $this->uniqueString(
            fn (): string => Str::upper(Str::random(8)),
            'code',
            ReferralCode::class,
        );
    }

    protected function uniqueCouponCode(string $prefix): string
    {
        return $this->uniqueString(
            fn (): string => $prefix.'-'.Str::upper(Str::random(6)),
            'coupon',
            Discount::class,
        );
    }

    /**
     * Sinh một chuỗi chưa có trong bảng, thử vài lần rồi bỏ cuộc.
     *
     * Va chạm là chuyện của xác suất, không phải của logic — mười lượt thử là
     * thừa, nhưng một vòng lặp vô hạn thì biến một va chạm thành một request treo.
     *
     * @param  callable():string  $make
     * @param  class-string<Model>  $model
     */
    protected function uniqueString(callable $make, string $column, string $model, int $attempts = 10): string
    {
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $candidate = $make();

            if (! $model::query()->where($column, $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException("Could not generate a unique {$column} after {$attempts} attempts.");
    }

    protected function percentage(string $key, int $default): int
    {
        return max(0, min(self::MAX_PERCENTAGE, (int) $this->settings->get($key, $default)));
    }

    protected function days(string $key, int $default): int
    {
        return max(1, (int) $this->settings->get($key, $default));
    }
}
