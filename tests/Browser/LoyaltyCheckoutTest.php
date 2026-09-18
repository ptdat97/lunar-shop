<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Lunar\Core\Models\Cart;
use Lunar\Core\Models\CartLine;
use Lunar\Core\Models\Customer;
use Lunar\Core\Models\Product;
use Modules\Core\Support\Settings;
use Modules\Customer\Services\CustomerResolver;
use Modules\Promotion\Models\LoyaltyEntry;
use Modules\Promotion\Services\LoyaltyService;
use Tests\DuskTestCase;

/**
 * Bấm "Áp dụng" ở ô tiêu điểm có thật sự đổi tổng tiền không (roadmap §16).
 *
 * `LoyaltyPointsTest` (Feature) đã chốt: endpoint trừ đúng, pipeline giỏ trừ sau
 * thuế, sổ cái ghi đúng bút toán. Nhưng **không test server nào chứng minh cái
 * nút hoạt động**: `enhance/checkout-loyalty.js` phải tìm đúng `data-loyalty-url`,
 * đọc đúng `data.loyalty` server trả về, rồi vẽ lại đúng ô `[data-sum-total]`.
 * Cả ba việc đó xảy ra trong trình duyệt, sau một cú bấm — không có request nào
 * để mà test ([e2e-testing.md §1](../../docs/guides/e2e-testing.md)).
 *
 * Test cố ý so TỔNG TIỀN trước và sau, chứ không khẳng định "ô tiêu điểm có
 * hiện". Ô hiện mà bấm không ăn thua là đúng cái bẫy "kiểm thứ dễ kiểm thay vì
 * thứ đúng" mà doc mô tả.
 *
 * Chạy trên DB dev nên phải dọn sạch (§3.4): tính năng điểm được bật tạm, tài
 * khoản + sổ cái + giỏ đều là đồ tạm và bị xoá trong `finally`.
 */
class LoyaltyCheckoutTest extends DuskTestCase
{
    private ?User $user = null;

    private ?string $settingsBackup = null;

    private bool $settingsExisted = false;

    public function test_applying_points_changes_the_grand_total(): void
    {
        $product = Product::query()->with('defaultUrl')->get()
            ->first(fn (Product $p) => $p->defaultUrl !== null
                && $p->variants->first()?->getTotalInventory() > 0);

        $this->assertNotNull($product, 'Không có sản phẩm còn hàng để thêm vào giỏ.');

        $this->enableLoyalty();

        try {
            $user = $this->shopperWithPoints();

            $this->browse(function (Browser $browser) use ($product, $user) {
                $browser->loginAs($user)
                    ->visit('/products/'.$product->defaultUrl->slug)
                    ->waitFor('[data-add-to-cart-btn]:not([disabled])', 15)
                    ->press('[data-add-to-cart-btn]')
                    ->waitFor('#shoppingCart [data-cart-body] [data-line]', 15)
                    ->visit('/checkout')
                    // Ô tiêu điểm chỉ render khi giỏ đã có customer — chính chỗ
                    // mà `CartClaimedOnLoginTest` canh ở tầng server.
                    ->waitFor('[data-loyalty-form]', 15);

                // Đọc tổng tiền bằng CHÍNH biểu thức mà `waitUntil` dùng.
                // `$browser->text()` trả về text đã render, `textContent` thì
                // không — so hai nguồn khác nhau thì điều kiện chờ đúng ngay lập
                // tức và test đi tiếp trong khi chưa có gì xảy ra cả.
                $read = 'document.querySelector("[data-sum-total]").textContent.trim()';
                $before = $browser->script("return {$read};")[0];

                $browser->type('[data-loyalty-input]', '20')
                    ->press('[data-loyalty-apply]')
                    // Chờ chính tổng tiền đổi, không chờ một khoảng thời gian.
                    ->waitUntil($read.' !== '.json_encode($before), 20);

                $after = $browser->script("return {$read};")[0];

                $this->assertNotSame($before, $after, 'Bấm áp dụng mà tổng tiền không đổi.');

                // Và dòng trạng thái phải nói bằng tiếng đã dịch, không phải
                // chuỗi cứng trong bundle.
                $this->assertStringContainsString(
                    '20',
                    trim($browser->text('[data-loyalty-status]')),
                    'Dòng trạng thái không nhắc tới số điểm vừa dùng.',
                );
            });
        } finally {
            $this->cleanUp();
        }
    }

    /** Bật điểm thưởng, nhớ nguyên trạng cũ để trả lại. */
    private function enableLoyalty(): void
    {
        $row = DB::table('app_settings')->where('key', 'loyalty')->first();

        $this->settingsExisted = $row !== null;
        $this->settingsBackup = $row?->value;

        // `point_value` = 1 đơn vị tiền LỚN mỗi điểm, không phải mặc định 200.
        // Mặc định của config tính theo ₫ (10.000₫ → 1 điểm, 1 điểm = 200₫);
        // DB dev lại chạy USD, nên 200 USD/điểm làm trần 50% của giỏ rơi xuống
        // 0 điểm và test trượt vì một lý do không liên quan tới cái nút.
        app(Settings::class)->put('loyalty', [
            'enabled' => true,
            'earn_per_amount' => 10,
            'point_value' => 1,
            'hold_days' => 14,
            'expire_days' => 365,
            'min_redeem' => 10,
            'max_percent' => 50,
        ]);
    }

    private function shopperWithPoints(): User
    {
        $this->user = User::create([
            'name' => 'Dusk Loyalty',
            'email' => 'dusk-loyalty-'.uniqid().'@example.test',
            'password' => bcrypt(str()->random(32)),
        ]);

        $customer = app(CustomerResolver::class)->forUser($this->user);

        $lot = app(LoyaltyService::class)->adjust($customer, 500, 'dusk fixture');
        $lot->update(['available_at' => now()->subMinute()]);

        return $this->user;
    }

    /**
     * Trả DB dev về nguyên trạng.
     *
     * Hai phần, và phần cài đặt nằm trong `finally` của phần dữ liệu: lần đầu
     * viết test này, dọn dữ liệu ném giữa chừng (FK) nên bước trả lại cài đặt
     * **không bao giờ chạy** — shop dev bị bỏ lại với điểm thưởng đang BẬT, và
     * mọi lần chạy sau đó tưởng đó là nguyên trạng rồi cần mẫn khôi phục nó.
     * Một test dọn dẹp nửa vời còn tệ hơn test không dọn, vì nó dọn đủ để không
     * ai nhận ra.
     */
    private function cleanUp(): void
    {
        try {
            $this->cleanUpData();
        } finally {
            $this->restoreSettings();
        }
    }

    private function restoreSettings(): void
    {
        if ($this->settingsExisted) {
            DB::table('app_settings')->where('key', 'loyalty')
                ->update(['value' => $this->settingsBackup]);
        } else {
            DB::table('app_settings')->where('key', 'loyalty')->delete();
        }

        app(Settings::class)->forgetCache();
    }

    private function cleanUpData(): void
    {
        if ($this->user) {
            $customerIds = $this->user->customers()->pluck('lunar_customers.id')->all();

            // Giỏ trước: `lunar_carts` có FK tới cả user lẫn customer, nên xoá
            // customer trước là gãy ràng buộc. Lọc theo CẢ HAI cột — phiên
            // duyệt có thể đã tạo một giỏ vãng lai rồi mới nhận về.
            //
            // `forceDelete` + `withTrashed`: Cart dùng SoftDeletes, nên `delete()`
            // để nguyên hàng trong bảng và FK vẫn giữ — dọn kiểu đó thì lần chạy
            // sau vẫn chết ở đúng chỗ này.
            $carts = Cart::withTrashed()
                ->where('user_id', $this->user->id)
                ->when($customerIds, fn ($q) => $q->orWhereIn('customer_id', $customerIds))
                ->get();

            // Dòng giỏ trước giỏ: `lunar_cart_lines` có FK tới `lunar_carts`.
            CartLine::whereIn('cart_id', $carts->pluck('id'))->delete();
            $carts->each->forceDelete();

            LoyaltyEntry::query()->whereIn('customer_id', $customerIds)->delete();
            $this->user->customers()->detach();
            Customer::query()->whereIn('id', $customerIds)->delete();
            $this->user->delete();

            $this->user = null;
        }
    }
}
