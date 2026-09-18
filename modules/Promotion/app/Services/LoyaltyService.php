<?php

namespace Modules\Promotion\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\DataObjects\PriceValue;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\Currency;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Order;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Promotion\Models\LoyaltyEntry;
use Modules\Promotion\Pipelines\Cart\RedeemLoyaltyPoints;

/**
 * Điểm thưởng: cộng sau khi hết hạn đổi/trả, tiêu lúc thanh toán, hết hạn theo lô.
 *
 * Hạng thành viên ({@see MembershipService}) là **thụ động** — khách không có
 * việc gì để làm, nên không có lý do quay lại. Điểm là phần chủ động: tiêu được
 * thì mới có lý do quay lại tiêu.
 *
 * ## Ba quyết định đáng ghi lại
 *
 * **1. Điểm là một hình thức THANH TOÁN, không phải một khuyến mãi.** Nó trừ vào
 * `total` SAU thuế, không đụng `discountTotal`, và chạy như một chặng cuối của
 * pipeline giỏ ({@see RedeemLoyaltyPoints}).
 * Hệ quả có chủ đích: tiêu điểm không làm giảm thuế phải nộp, và vì điểm mới
 * tính trên SỐ TIỀN THẬT khách trả, tiêu điểm cũng không đẻ ra điểm — vòng lặp
 * tự nuôi bị chặn bởi chính chỗ đặt phép trừ, không phải bởi một luật thêm vào.
 *
 * **2. Không có lệnh "phát điểm".** Giới thiệu bạn (§15) cần `referrals:release`
 * vì phần thưởng ở đó là một coupon phải được TẠO. Điểm thì chỉ cần một ngày:
 * `available_at` nằm ngoài số dư cho tới khi tới hạn. Đơn bị trả lại trong lúc
 * chờ thì lô bị thu hồi khi còn nguyên — đó là lý do hạn chờ tồn tại.
 *
 * **3. Tiêu điểm ăn theo FIFO THEO HẠN, không theo thứ tự nhận.** Lô sắp chết
 * trước bị ăn trước. Ăn theo thứ tự nhận nghe "công bằng" hơn nhưng làm điểm
 * của khách chết oan trong khi lô mới vẫn còn hạn dài.
 *
 * ## Chỗ còn hở, ghi ra chứ không giấu
 *
 * Hai lượt thanh toán ĐỒNG THỜI của cùng một khách (hai tab, hai giỏ) có thể
 * tiêu quá số dư: giỏ kẹp số điểm theo số dư lúc TÍNH, còn bút toán trừ được
 * ghi lúc đơn đã tạo xong. Kết quả là số dư âm — **nhìn thấy được và đối soát
 * được**, và khách không tiêu tiếp được cho tới khi nó dương lại. Đổi lấy điều
 * đó là không phải dựng thêm một vòng đời "giữ chỗ điểm" song song với giữ chỗ
 * tồn kho. Với shop một cửa hàng thì đây là đánh đổi đúng; nếu sau này sai, chỗ
 * phải sửa là {@see self::commitRedemption()}.
 */
class LoyaltyService
{
    /** Khoá trong `cart.meta` / `order.meta` giữ số điểm khách chọn tiêu. */
    public const META_KEY = 'loyalty_points';

    /** Lý do cho bút toán thu hồi khi đơn bị trả lại. */
    public const REASON_RETURNED = 'order-returned';

    /** Lý do cho bút toán trả lại điểm khi đơn bị huỷ/hoàn. */
    public const REASON_ORDER_CLOSED = 'order-closed';

    public const REASON_EXPIRED = 'expired';

    /** Trần một lượt quét hết hạn, để cron không cắn một miếng quá to. */
    public const EXPIRY_BATCH = 500;

    public function __construct(
        protected Settings $settings,
        protected CustomerResolver $customers,
    ) {}

    // ------------------------------------------------------------------ config

    public function enabled(): bool
    {
        return (bool) $this->settings->get('loyalty.enabled', false);
    }

    /** Chi bao nhiêu (đơn vị tiền LỚN) thì được 1 điểm. */
    public function earnPerAmount(): int
    {
        return max(1, (int) $this->settings->get('loyalty.earn_per_amount', 10000));
    }

    /** 1 điểm đáng bao nhiêu tiền (đơn vị tiền LỚN) khi tiêu. */
    public function pointValue(): int
    {
        return max(0, (int) $this->settings->get('loyalty.point_value', 200));
    }

    /** Chờ bao lâu sau khi trả tiền thì điểm mới tiêu được — hạn đổi/trả của shop. */
    public function holdDays(): int
    {
        return max(0, (int) $this->settings->get('loyalty.hold_days', 14));
    }

    /** Điểm sống bao lâu kể từ lúc khả dụng. 0 = không hết hạn. */
    public function expireDays(): int
    {
        return max(0, (int) $this->settings->get('loyalty.expire_days', 365));
    }

    /** Tiêu ít nhất bao nhiêu điểm một lần. */
    public function minRedeem(): int
    {
        return max(1, (int) $this->settings->get('loyalty.min_redeem', 10));
    }

    /**
     * Tiêu tối đa bao nhiêu phần trăm giá trị đơn.
     *
     * Trần này **không phải để tiết kiệm**: một đơn trả 100% bằng điểm là một
     * đơn 0 đồng, mà cổng thanh toán không nhận số tiền 0 — lỗi sẽ nổ ở đúng
     * bước cuối cùng của khách.
     */
    public function maxPercent(): int
    {
        return min(100, max(1, (int) $this->settings->get('loyalty.max_percent', 50)));
    }

    // ----------------------------------------------------------------- số dư

    /** Số dư tiêu được ngay. */
    public function balanceFor(Customer $customer): int
    {
        return (int) LoyaltyEntry::query()
            ->where('customer_id', $customer->id)
            ->available()
            ->sum('points');
    }

    /** Điểm đã ghi nhưng chưa tới ngày khả dụng. */
    public function pendingFor(Customer $customer): int
    {
        return (int) LoyaltyEntry::query()
            ->where('customer_id', $customer->id)
            ->pending()
            ->sum('points');
    }

    /** Phần chưa bị ăn của một lô. */
    public function lotRemaining(LoyaltyEntry $lot): int
    {
        return $lot->points + (int) LoyaltyEntry::query()->where('lot_id', $lot->id)->sum('points');
    }

    // ------------------------------------------------------------------ cộng

    /**
     * Ghi điểm cho một đơn vừa được trả tiền.
     *
     * Chỉ ĐÁNH DẤU, chưa cho tiêu: lô mang `available_at = now + holdDays`.
     * Idempotent theo đơn — `OrderPaid` có thể bắn lại (callback cổng thanh
     * toán vào hai lần là chuyện bình thường, xem GatewayReconciler).
     */
    public function earnFor(Order $order): ?LoyaltyEntry
    {
        if (! $this->enabled() || ! $order->customer_id) {
            return null;
        }

        if ($this->entryFor($order, LoyaltyEntry::EARN)) {
            return null;
        }

        // `lunar_orders.total` cast là INTEGER (đơn vị nhỏ), không phải
        // PriceValue như `cart->total` — cùng tên, khác kiểu. Cả repo đọc nó
        // bằng `(int) $order->total` (GatewayReconciler, ReturnService).
        $points = $this->pointsEarnedOn((int) $order->total);

        if ($points <= 0) {
            return null;
        }

        $availableAt = now()->addDays($this->holdDays());

        return LoyaltyEntry::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'type' => LoyaltyEntry::EARN,
            'points' => $points,
            'available_at' => $availableAt,
            'expires_at' => $this->expireDays() > 0
                ? $availableAt->copy()->addDays($this->expireDays())
                : null,
        ]);
    }

    /**
     * Số điểm một khoản tiền (đơn vị NHỎ) sinh ra.
     *
     * Làm tròn XUỐNG: cho thừa một điểm ở mỗi đơn là một khoản lỗ không ai ghi
     * vào đâu cả.
     */
    public function pointsEarnedOn(int $paidMinor): int
    {
        if ($paidMinor <= 0) {
            return 0;
        }

        return (int) floor(($paidMinor / $this->factor()) / $this->earnPerAmount());
    }

    /**
     * Thu hồi điểm của một đơn đã trả lại / hoàn tiền.
     *
     * Thu hồi phần CÒN LẠI của lô, không phải toàn bộ lô: đường đi thường ngày
     * thì lô vẫn đang chờ nên còn nguyên, nhưng nếu hạn chờ đã qua và khách đã
     * tiêu mất một phần thì kéo số dư xuống âm vì một đơn cũ là phạt nhầm người.
     */
    public function revokeForOrder(Order $order): ?LoyaltyEntry
    {
        $lot = $this->entryFor($order, LoyaltyEntry::EARN);

        if (! $lot) {
            return null;
        }

        $remaining = $this->lotRemaining($lot);

        if ($remaining <= 0) {
            return null;
        }

        return $this->debit($lot, LoyaltyEntry::REVOKE, $remaining, $order->id, self::REASON_RETURNED);
    }

    // ------------------------------------------------------------------- tiêu

    /** Số điểm khách đã chọn tiêu cho giỏ này, đã kẹp lại theo mọi trần. */
    public function redemptionFor(Cart $cart): int
    {
        $requested = (int) data_get($cart->meta, self::META_KEY, 0);

        if ($requested <= 0) {
            return 0;
        }

        return min($requested, $this->maxRedeemableFor($cart));
    }

    /**
     * Trần điểm tiêu được cho giỏ này.
     *
     * Ba trần cùng lúc: số dư của khách, phần trăm giá trị đơn, và bản thân
     * tổng tiền (điểm không được biến tổng thành số âm). Kẹp ở ĐÂY chứ không ở
     * controller, vì giỏ được tính lại nhiều lần giữa lúc chọn và lúc đặt — số
     * dư có thể đã đổi.
     */
    public function maxRedeemableFor(Cart $cart): int
    {
        $customer = $cart->customer;

        if (! $this->enabled() || ! $customer || $this->pointValue() <= 0) {
            return 0;
        }

        $total = (int) ($cart->total?->value ?? 0);

        if ($total <= 0) {
            return 0;
        }

        $cap = (int) floor($total * $this->maxPercent() / 100);
        $perPoint = $this->pointValue() * $this->factor();

        return max(0, min(
            $this->balanceFor($customer),
            (int) floor($cap / $perPoint),
        ));
    }

    /** Giá trị tiền (đơn vị NHỎ) của một số điểm. */
    public function moneyValue(int $points): int
    {
        return max(0, $points) * $this->pointValue() * $this->factor();
    }

    public function priceValue(int $points, ?Currency $currency = null): PriceValue
    {
        return new PriceValue($this->moneyValue($points), $currency ?? Currency::getDefault());
    }

    /**
     * Ghi số điểm khách muốn tiêu vào giỏ và tính lại.
     *
     * `meta` chứ không phải một cột riêng: `FillOrderFromCart` chép nguyên
     * `cart.meta` sang `order.meta`, nên số điểm đi theo đơn mà không phải thêm
     * một bước nào ở tầng checkout.
     */
    public function redeem(Cart $cart, int $points): Cart
    {
        $meta = (array) ($cart->meta ?? []);

        if ($points <= 0) {
            unset($meta[self::META_KEY]);
        } else {
            $meta[self::META_KEY] = $points;
        }

        $cart->update(['meta' => $meta]);

        return $cart->fresh()->recalculate();
    }

    /**
     * Ghi các bút toán trừ cho một đơn vừa được tạo.
     *
     * FIFO theo hạn, và có thể tách làm nhiều dòng — mỗi dòng ăn vào một lô.
     * Đó chính là lý do sổ cái có `lot_id`: không có nó thì tới lúc hết hạn
     * không ai biết lô nào còn lại bao nhiêu.
     *
     * Idempotent theo đơn, vì `OrderPlaced` không hứa chỉ bắn một lần.
     */
    public function commitRedemption(Order $order): int
    {
        if (! $order->customer_id) {
            return 0;
        }

        $points = (int) data_get($order->meta, self::META_KEY, 0);

        if ($points <= 0 || $this->entryFor($order, LoyaltyEntry::SPEND)) {
            return 0;
        }

        return DB::transaction(function () use ($order, $points): int {
            $spent = 0;

            foreach ($this->consumableLots($order->customer_id) as $lot) {
                if ($spent >= $points) {
                    break;
                }

                $take = min($points - $spent, $this->lotRemaining($lot));

                if ($take <= 0) {
                    continue;
                }

                $this->debit($lot, LoyaltyEntry::SPEND, $take, $order->id);

                $spent += $take;
            }

            return $spent;
        });
    }

    /**
     * Trả lại điểm đã tiêu khi đơn bị huỷ hoặc hoàn tiền.
     *
     * Trả thành một LÔ MỚI với hạn tính lại từ hôm nay, không hồi sinh các lô
     * cũ. Hồi sinh đúng hơn về mặt kế toán nhưng cần lần ngược từng dòng trừ để
     * biết trả vào đâu, và kết quả có thể là trả lại điểm đã hết hạn trong lúc
     * đơn đang treo — tức là trả một thứ vô dụng. Một lô mới thì khách dùng
     * được, và sổ vẫn cân.
     */
    public function refundRedemption(Order $order): ?LoyaltyEntry
    {
        if (! $order->customer_id || $this->entryFor($order, LoyaltyEntry::REFUND)) {
            return null;
        }

        $spent = abs((int) LoyaltyEntry::query()
            ->where('order_id', $order->id)
            ->where('type', LoyaltyEntry::SPEND)
            ->sum('points'));

        if ($spent <= 0) {
            return null;
        }

        return LoyaltyEntry::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'type' => LoyaltyEntry::REFUND,
            'points' => $spent,
            'available_at' => now(),
            'expires_at' => $this->expireDays() > 0 ? now()->addDays($this->expireDays()) : null,
            'reason' => self::REASON_ORDER_CLOSED,
        ]);
    }

    // ---------------------------------------------------------------- hết hạn

    /**
     * Ghi bút toán hết hạn cho mọi lô đã qua `expires_at` mà còn dư.
     *
     * Trả về tổng số điểm đã bị đóng. Chạy hằng ngày; an toàn khi chạy lại
     * nhiều lần vì một lô đã bị đóng thì phần còn lại bằng 0.
     */
    public function expireLots(): int
    {
        $closed = 0;

        $lots = LoyaltyEntry::query()
            ->whereIn('type', [LoyaltyEntry::EARN, LoyaltyEntry::REFUND, LoyaltyEntry::ADJUST])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('points', '>', 0)
            ->orderBy('expires_at')
            ->limit(self::EXPIRY_BATCH)
            ->get();

        foreach ($lots as $lot) {
            $remaining = $this->lotRemaining($lot);

            if ($remaining <= 0) {
                continue;
            }

            $this->debit($lot, LoyaltyEntry::EXPIRE, $remaining, reason: self::REASON_EXPIRED);

            $closed += $remaining;
        }

        return $closed;
    }

    // ------------------------------------------------------------------- staff

    /** Staff cộng/trừ tay. Cộng thì thành một lô mới, trừ thì ăn FIFO như tiêu. */
    public function adjust(Customer $customer, int $points, ?string $reason = null): ?LoyaltyEntry
    {
        if ($points === 0) {
            return null;
        }

        if ($points > 0) {
            return LoyaltyEntry::create([
                'customer_id' => $customer->id,
                'type' => LoyaltyEntry::ADJUST,
                'points' => $points,
                'available_at' => now(),
                'expires_at' => $this->expireDays() > 0 ? now()->addDays($this->expireDays()) : null,
                'reason' => $reason,
            ]);
        }

        $last = null;
        $left = abs($points);

        foreach ($this->consumableLots($customer->id) as $lot) {
            if ($left <= 0) {
                break;
            }

            $take = min($left, $this->lotRemaining($lot));

            if ($take <= 0) {
                continue;
            }

            $last = $this->debit($lot, LoyaltyEntry::ADJUST, $take, reason: $reason);

            $left -= $take;
        }

        return $last;
    }

    // --------------------------------------------------------------- hiển thị

    /**
     * Dữ liệu cho khối điểm thưởng trên trang tài khoản, hoặc null khi tắt.
     *
     * @return array<string, mixed>|null
     */
    public function viewDataFor(User $user): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $customer = $this->customers->existingForUser($user);

        if (! $customer) {
            return null;
        }

        $balance = $this->balanceFor($customer);

        return [
            'balance' => $balance,
            'pending' => $this->pendingFor($customer),
            'value' => $this->priceValue($balance)->format(),
            'expiring' => $this->expiringSoonFor($customer),
            'point_value' => $this->priceValue(1)->format(),
            'min_redeem' => $this->minRedeem(),
        ];
    }

    /**
     * Điểm thưởng đang áp cho một giỏ — hợp đồng dùng chung cho `CartResource`
     * (JSON) và trang thanh toán (Blade SSR).
     *
     * Một hàm, hai người đọc: đó là cách duy nhất để dòng "−X điểm" trên trang
     * thanh toán và dòng cùng tên trong JSON không bao giờ nói hai con số khác
     * nhau sau một lần sửa.
     *
     * Trả null cho CẢ BA ca "không dùng được" (tắt, khách vãng lai, số dư 0 mà
     * chưa áp gì) thay vì một khối toàn số 0: người đọc chỉ phải hỏi một câu, và
     * một khối 0 điểm giữa trang thanh toán là thứ gây chú ý mà không dùng được
     * vào việc gì.
     *
     * @return array<string, mixed>|null
     */
    public function cartInfo(Cart $cart): ?array
    {
        $customer = $cart->customer;

        if (! $this->enabled() || ! $customer) {
            return null;
        }

        $balance = $this->balanceFor($customer);
        $applied = $this->redemptionFor($cart);

        if ($balance <= 0 && $applied <= 0) {
            return null;
        }

        $currency = $cart->currency ?? Currency::getDefault();

        return [
            'balance' => $balance,
            'applied' => $applied,
            'applied_value' => $this->priceValue($applied, $currency)->format(),
            'max' => $this->maxRedeemableFor($cart),
            'min' => $this->minRedeem(),
            'point_value' => $this->priceValue(1, $currency)->format(),
        ];
    }

    /**
     * Điểm sắp hết hạn trong 30 ngày tới, hoặc null khi không có.
     *
     * @return array{points:int, at:string}|null
     */
    public function expiringSoonFor(Customer $customer): ?array
    {
        $lots = LoyaltyEntry::query()
            ->where('customer_id', $customer->id)
            ->where('points', '>', 0)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->orderBy('expires_at')
            ->get();

        $points = $lots->sum(fn (LoyaltyEntry $lot) => max(0, $this->lotRemaining($lot)));

        if ($points <= 0) {
            return null;
        }

        return [
            'points' => (int) $points,
            'at' => $lots->first()->expires_at->translatedFormat('d/m/Y'),
        ];
    }

    // ------------------------------------------------------------------ nội bộ

    /**
     * Ghi một bút toán TRỪ vào một lô.
     *
     * **Bút toán trừ thừa hưởng `available_at` của lô nó ăn vào.** Đây không
     * phải chi tiết nhỏ mà là bất biến của sổ: số dư chỉ cộng những dòng đã tới
     * hạn, nên một dòng trừ "có hiệu lực ngay" đặt lên một lô CÒN ĐANG CHỜ sẽ
     * kéo số dư xuống âm trong khi phần chờ vẫn nguyên — thu hồi điểm của một
     * đơn vừa bị trả lại, đúng đường đi thường ngày, đã cho ra số dư −10.
     *
     * Đi qua một chỗ duy nhất vì cả bốn đường trừ (tiêu, hết hạn, thu hồi, staff
     * trừ tay) đều phải theo cùng luật đó.
     */
    protected function debit(
        LoyaltyEntry $lot,
        string $type,
        int $points,
        ?int $orderId = null,
        ?string $reason = null,
    ): LoyaltyEntry {
        return LoyaltyEntry::create([
            'customer_id' => $lot->customer_id,
            'lot_id' => $lot->id,
            'order_id' => $orderId,
            'type' => $type,
            'points' => -abs($points),
            'available_at' => $lot->available_at,
            'reason' => $reason,
        ]);
    }

    /**
     * Các lô còn dùng được của một khách, theo thứ tự FIFO-theo-hạn.
     *
     * Lô không hạn xếp cuối (`expires_at` NULL): chúng không chết, nên để dành.
     *
     * @return Collection<int, LoyaltyEntry>
     */
    protected function consumableLots(int $customerId): Collection
    {
        return LoyaltyEntry::query()
            ->where('customer_id', $customerId)
            ->whereIn('type', [LoyaltyEntry::EARN, LoyaltyEntry::REFUND, LoyaltyEntry::ADJUST])
            ->where('points', '>', 0)
            ->available()
            ->orderByRaw('expires_at IS NULL, expires_at ASC')
            ->orderBy('id')
            ->get();
    }

    /** Bút toán loại $type đã ghi cho đơn này chưa — chốt chống ghi hai lần. */
    protected function entryFor(Order $order, string $type): ?LoyaltyEntry
    {
        return LoyaltyEntry::query()
            ->where('order_id', $order->id)
            ->where('type', $type)
            ->first();
    }

    protected function factor(): int
    {
        return (int) (Currency::getDefault()?->factor ?: 100);
    }
}
