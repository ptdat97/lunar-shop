<?php

namespace Modules\Promotion\Http\Controllers\Storefront;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\Promotion\Services\ReferralService;

/**
 * `/r/{code}` — điểm đến của link mời.
 *
 * Không render gì: nhiệm vụ duy nhất là bắt mã rồi đưa khách về nơi họ định
 * tới. Một trang "bạn được giới thiệu!" ở giữa chỉ thêm một cú bấm cho khách.
 */
class ReferralLinkController extends Controller
{
    public function __construct(protected ReferralService $referrals) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        // Mã sai hay cũ cũng đưa về trang chủ bình thường: đây là link khách
        // chia sẻ cho nhau, không phải endpoint để báo lỗi.
        $this->referrals->capture($code);

        return redirect()->to($this->target($request) ?? route('storefront.home'));
    }

    /**
     * `?to=/san-pham/ao-thun` — chỉ nhận ĐƯỜNG DẪN nội bộ, không nhận URL đầy đủ.
     *
     * Nhận URL đầy đủ ở đây là mở luôn một open redirect, đúng thứ mà một link
     * được chia sẻ rộng rãi không nên có: kẻ xấu gửi `?to=https://hang-gia.example`
     * kèm link giới thiệu thật, và shop tự tay redirect khách sang đó.
     */
    protected function target(Request $request): ?string
    {
        $to = (string) $request->query('to', '');

        if ($to === '' || ! Str::startsWith($to, '/') || Str::startsWith($to, '//')) {
            return null;
        }

        return $to;
    }
}
