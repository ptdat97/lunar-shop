<?php

namespace Modules\Promotion\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Events\Orders\OrderPlaced;
use Lunar\Core\Facades\Discounts;
use Modules\Content\Services\SectionRenderer;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Support\OrderStatus;
use Modules\Promotion\Console\BackfillMembershipTiers;
use Modules\Promotion\Console\ExpireLoyaltyPoints;
use Modules\Promotion\Console\ReleaseReferralRewards;
use Modules\Promotion\Http\Resources\PromotionResource;
use Modules\Promotion\Panel\LoyaltyEntryResource;
use Modules\Promotion\Panel\LoyaltySettings;
use Modules\Promotion\Panel\MembershipSettings;
use Modules\Promotion\Panel\ReferralResource;
use Modules\Promotion\Panel\ReferralSettings;
use Modules\Promotion\Pipelines\Cart\RedeemLoyaltyPoints;
use Modules\Promotion\Services\LoyaltyService;
use Modules\Promotion\Services\MembershipService;
use Modules\Promotion\Services\PromotionService;
use Modules\Promotion\Services\ReferralService;

class PromotionServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/promotion.php', 'promotion');
        // Giới thiệu bạn có group cài đặt riêng (`referral`) — không nằm chung
        // group `promotion` với hạng thành viên, vì `Settings::put()` thay CẢ
        // group: hai trang cài đặt dùng chung một group thì lưu trang này xoá
        // khoá của trang kia.
        $this->mergeConfigFrom(__DIR__.'/../../config/referral.php', 'referral');
        // Điểm thưởng cũng có group cài đặt riêng (`loyalty`), cùng lý do.
        $this->mergeConfigFrom(__DIR__.'/../../config/loyalty.php', 'loyalty');

        // Singleton so per-request memoization in the service (active automatic
        // discounts + their eager-loaded relations) is shared across the many
        // saleFor() calls product cards trigger on a listing page.
        $this->app->singleton(PromotionService::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, discount types.
     */
    public function boot(): void
    {
        // Nhóm cài đặt của module trên panel Lunar.
        $this->app->make(SettingsRegistry::class)->add(new MembershipSettings);
        $this->app->make(SettingsRegistry::class)->add(new ReferralSettings);
        $this->app->make(SettingsRegistry::class)->add(new LoyaltySettings);

        // Hàng đợi giới thiệu: staff chỉ xem, đóng lượt gian lận, hoặc phát
        // thưởng sớm — không tạo/sửa được một lượt giới thiệu nào.
        $this->app->make(ResourceRegistry::class)->add(new ReferralResource);

        // Sổ cái điểm: chỉ đọc. Một dòng đã ghi là một sự kiện đã xảy ra —
        // muốn đổi số dư thì ghi thêm bút toán, không sửa dòng cũ.
        $this->app->make(ResourceRegistry::class)->add(new LoyaltyEntryResource);

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'promotion-admin');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->registerDiscountTypes();
        $this->registerMembershipSync();
        $this->registerReferrals();
        $this->registerLoyalty();
        $this->shareFlashSale();
        $this->serializePromotionsStrip();

        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillMembershipTiers::class,
                ReleaseReferralRewards::class,
                ExpireLoyaltyPoints::class,
            ]);
        }
    }

    /**
     * JSON for the `promotions-strip` section (GET /api/v1/home-feed).
     *
     * This section has no SectionRenderer provider — the Blade partial is fed by
     * the view composer in shareFlashSale(), which a JSON response never runs.
     * So the serializer reads the service directly. It lives here, not in
     * Content, because Promotion owns the data and the Resource.
     */
    protected function serializePromotionsStrip(): void
    {
        $this->app->make(SectionRenderer::class)->serialize(
            'promotions-strip',
            fn () => [
                'promotions' => PromotionResource::collection(
                    $this->app->make(PromotionService::class)->activeAutomatic(),
                )->resolve(),
            ],
        );
    }

    /**
     * Feed promotion data into storefront views so Blade never resolves this
     * service itself (coding standards §7). Each composer reads the model/cart
     * already in the view and adds the promotion view-data:
     *  - promo bar          → $flashSale
     *  - promotions strip    → $promotions list + a describe() closure
     *  - product card/price  → $sale (badge + price break)
     *  - checkout summary    → $appliedDiscounts
     */
    protected function shareFlashSale(): void
    {
        $promotions = fn () => $this->app->make(PromotionService::class);

        View::composer('theme::partials.promo-bar', function ($view) use ($promotions): void {
            $svc = $promotions();
            $flashSale = $svc->currentFlashSale();
            $view->with([
                'flashSale' => $flashSale,
                'flashSaleDescription' => $flashSale ? $svc->describe($flashSale) : null,
            ]);
        });

        View::composer([
            'theme::partials.promotions-strip',
            'theme::sections.promotions-strip',
        ], function ($view) use ($promotions): void {
            $svc = $promotions();
            $view->with([
                'promotionsList' => $svc->activeAutomatic(),
                'describePromotion' => fn ($discount) => $svc->describe($discount),
            ]);
        });

        // $sale for the product card + price component + product page (badge +
        // struck price). All three read $product already in the view.
        // Uses per-request memoization inside PromotionService so the first
        // call pre-loads relations; subsequent calls for the same product are
        // instant (zero queries).
        View::composer(
            ['theme::components.product-card', 'theme::components.price', 'theme::pages.product'],
            function ($view) use ($promotions): void {
                $product = $view->getData()['product'] ?? null;
                $view->with('sale', $product ? $promotions()->saleFor($product) : null);
            },
        );

        View::composer('theme::pages.checkout', function ($view) use ($promotions): void {
            $cart = $view->getData()['cart'] ?? null;
            $view->with('appliedDiscounts', $cart ? $promotions()->appliedTo($cart) : []);
        });
    }

    /**
     * Re-evaluate a customer's loyalty tier whenever one of their orders is
     * paid. Listens to the Order module's OrderPaid domain event.
     */
    protected function registerMembershipSync(): void
    {
        Event::listen(OrderPaid::class, function (OrderPaid $event): void {
            $customer = $event->order?->customer;

            if ($customer) {
                app(MembershipService::class)->syncCustomer($customer);
            }
        });
    }

    /**
     * Giới thiệu bạn: ghi nhận người được mời lúc họ đăng ký, và đánh dấu đơn
     * đủ điều kiện khi đơn đó được trả tiền.
     *
     * Nghe `Registered` của Laravel thay vì sửa AuthController: đăng ký có hai
     * đường vào (cookie SPA và token cho app), một listener ở đây phục vụ cả
     * hai mà không thêm lời gọi nào vào luồng đăng nhập.
     *
     * Nghe `OrderPaid` chỉ để ĐÁNH DẤU — thưởng chưa được phát ở đây, vì đơn
     * vẫn còn trong hạn đổi/trả. `referrals:release` mới là chỗ phát.
     */
    protected function registerReferrals(): void
    {
        Event::listen(Registered::class, function (Registered $event): void {
            $request = request();
            $referrals = app(ReferralService::class);

            $referrals->claim(
                $event->user,
                fingerprint: $request instanceof Request ? $referrals->fingerprintFor($request) : null,
            );
        });

        Event::listen(OrderPaid::class, function (OrderPaid $event): void {
            app(ReferralService::class)->markQualifyingOrder($event->order);
        });

        // Khối giới thiệu trên trang tài khoản render sẵn từ server — cùng cách
        // mà đánh giá sản phẩm làm (SSR trước, JS chỉ enhance): không có JS thì
        // khách vẫn đọc được mã và link của mình.
        View::composer('theme::pages.account', function ($view): void {
            $user = $view->getData()['user'] ?? null;
            $request = request();

            $view->with(
                'referral',
                $user
                    ? app(ReferralService::class)->viewDataFor($user, $request instanceof Request ? $request : null)
                    : null,
            );
        });
    }

    /**
     * Điểm thưởng: cắm chặng trừ điểm vào pipeline giỏ, và bốn cái móc vòng đời.
     *
     * **Chặng pipeline được APPEND, không phải ghi đè.** Nó phải chạy SAU
     * `Calculate` của Lunar, vì `Calculate` dựng lại `total` từ dòng hàng + phí
     * ship và sẽ xoá mất phép trừ nào đặt trước nó. Nối đuôi danh sách là cách
     * duy nhất diễn đạt "cuối cùng" mà không phải chép lại cả danh sách của
     * Lunar — chép lại thì bản nâng cấp nào thêm chặng mới là ta lặng lẽ mất nó.
     *
     * Bốn móc, và mỗi cái trả lời một câu khác nhau:
     *
     * - `OrderPaid`  → ghi điểm, nhưng CHƯA cho tiêu (chờ hết hạn đổi/trả);
     * - `OrderPlaced` (của Lunar) → ghi bút toán TRỪ cho số điểm đã tiêu. Ở lúc
     *   đặt đơn chứ không phải lúc trả tiền: điểm đã thành tiền trên tổng đơn
     *   rồi, nên nó phải rời sổ ngay lúc đó;
     * - đơn bị trả lại / hoàn tiền → thu hồi điểm đã cộng;
     * - đơn đóng lại (huỷ/hoàn) → trả lại điểm đã tiêu. Hai việc ngược chiều
     *   nhau và đều phải xảy ra: một đơn vừa được thưởng vừa được tiêu điểm thì
     *   huỷ nó phải cuốn cả hai.
     */
    protected function registerLoyalty(): void
    {
        Config::set('lunar.cart.pipelines.cart', [
            ...array_diff(
                (array) config('lunar.cart.pipelines.cart', []),
                [RedeemLoyaltyPoints::class],
            ),
            RedeemLoyaltyPoints::class,
        ]);

        Event::listen(OrderPaid::class, function (OrderPaid $event): void {
            app(LoyaltyService::class)->earnFor($event->order);
        });

        Event::listen(OrderPlaced::class, function (OrderPlaced $event): void {
            app(LoyaltyService::class)->commitRedemption($event->order);
        });

        Event::listen(OrderStatusUpdated::class, function (OrderStatusUpdated $event): void {
            $loyalty = app(LoyaltyService::class);
            $order = $event->order;

            // Trả lại / hoàn tiền: điểm đã thưởng cho đơn đó không còn lý do tồn
            // tại. Dùng CHUNG một luật với email xin đánh giá và thưởng giới
            // thiệu bạn — `wasReturnedOrRefunded` là nơi duy nhất định nghĩa nó.
            if (OrderStatus::wasReturnedOrRefunded($order)) {
                $loyalty->revokeForOrder($order);
            }

            // Đơn đã đóng mà không giao được: điểm khách đã TIÊU vào nó phải
            // quay về. Khác câu hỏi ở trên, nên là một nhánh riêng chứ không
            // phải `else`.
            if (OrderStatus::isClosed($order)) {
                $loyalty->refundRedemption($order);
            }
        });

        // Khối điểm trên trang tài khoản render sẵn từ server, cùng cách khối
        // giới thiệu bạn làm: không có JS thì khách vẫn đọc được số dư.
        View::composer('theme::pages.account', function ($view): void {
            $user = $view->getData()['user'] ?? null;

            $view->with('loyalty', $user ? app(LoyaltyService::class)->viewDataFor($user) : null);
        });

        // Ô tiêu điểm ở trang thanh toán. Đọc ĐÚNG hàm mà CartResource đọc, nên
        // con số trên trang và con số trong JSON không thể lệch nhau.
        View::composer('theme::pages.checkout', function ($view): void {
            $cart = $view->getData()['cart'] ?? null;

            $view->with('loyalty', $cart ? app(LoyaltyService::class)->cartInfo($cart) : null);
        });
    }

    /**
     * Register fashion-specific discount types with Lunar's DiscountManager so
     * they show up in the admin and run in the cart pipeline alongside
     * the native AmountOff / BuyXGetY types.
     */
    protected function registerDiscountTypes(): void
    {
        foreach (config('promotion.discount_types', []) as $type) {
            Discounts::addType($type);
        }
    }
}
