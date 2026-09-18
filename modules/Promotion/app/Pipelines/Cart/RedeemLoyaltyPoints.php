<?php

namespace Modules\Promotion\Pipelines\Cart;

use Closure;
use Lunar\Core\Models\Cart;
use Modules\Promotion\Services\LoyaltyService;

/**
 * Trừ điểm khách chọn tiêu ra khỏi tổng tiền giỏ.
 *
 * Chạy **sau** `Calculate` của Lunar — chặng cuối cùng của
 * `lunar.cart.pipelines.cart`. Đó không phải chi tiết kỹ thuật mà là quyết định
 * mô hình: `Calculate` dựng `total` từ các dòng hàng + phí ship, nên bất cứ thứ
 * gì chạy trước nó đều bị nó ghi đè. Đặt ở cuối vì **điểm là một hình thức
 * thanh toán, không phải một khuyến mãi**:
 *
 * - không đụng `discountTotal` → phần "bạn đã tiết kiệm" vẫn chỉ nói về khuyến
 *   mãi thật, không trộn lẫn với tiền khách tự trả bằng điểm;
 * - trừ SAU thuế → tiêu điểm không làm giảm thuế phải nộp;
 * - và vì điểm mới được tính trên `total` cuối cùng, tiêu điểm cũng không đẻ ra
 *   điểm. Vòng lặp tự nuôi bị chặn bởi chính chỗ đặt phép trừ.
 *
 * Số điểm được kẹp lại ở {@see LoyaltyService::redemptionFor()} mỗi lần tính,
 * không phải một lần lúc khách bấm: giỏ tính lại nhiều lần giữa lúc chọn và lúc
 * đặt, và số dư có thể đã đổi.
 */
class RedeemLoyaltyPoints
{
    public function __construct(
        protected LoyaltyService $loyalty,
    ) {}

    /**
     * @param  Closure(Cart):mixed  $next
     */
    public function handle(Cart $cart, Closure $next): mixed
    {
        $points = $this->loyalty->redemptionFor($cart);

        if ($points > 0 && $cart->total) {
            $cart->total = $cart->total
                ->subtract($this->loyalty->priceValue($points, $cart->currency))
                // Trần phần trăm đã chặn từ trước, nhưng một tổng âm là loại lỗi
                // đi thẳng vào cổng thanh toán — kẹp ở đây là rẻ.
                ->clampToZero();
        }

        return $next($cart);
    }
}
