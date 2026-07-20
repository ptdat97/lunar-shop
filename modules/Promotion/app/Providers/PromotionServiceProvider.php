<?php

namespace Modules\Promotion\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Facades\Discounts;
use Modules\Content\Services\SectionRenderer;
use Modules\Core\Support\AdminPages;
use Modules\Order\Events\OrderPaid;
use Modules\Promotion\Console\BackfillMembershipTiers;
use Modules\Promotion\Filament\Pages\MembershipSettingsPage;
use Modules\Promotion\Http\Resources\PromotionResource;
use Modules\Promotion\Services\MembershipService;
use Modules\Promotion\Services\PromotionService;

class PromotionServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/promotion.php', 'promotion');

        // Singleton so per-request memoization in the service (active automatic
        // discounts + their eager-loaded relations) is shared across the many
        // saleFor() calls product cards trigger on a listing page.
        $this->app->singleton(PromotionService::class);

        // Admin page to configure spend-based membership tiers.
        AdminPages::add(MembershipSettingsPage::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, discount types.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'promotion-admin');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        $this->registerDiscountTypes();
        $this->registerMembershipSync();
        $this->shareFlashSale();
        $this->serializePromotionsStrip();

        if ($this->app->runningInConsole()) {
            $this->commands([BackfillMembershipTiers::class]);
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
     * Register fashion-specific discount types with Lunar's DiscountManager so
     * they show up in the Filament admin and run in the cart pipeline alongside
     * the native AmountOff / BuyXGetY types.
     */
    protected function registerDiscountTypes(): void
    {
        foreach (config('promotion.discount_types', []) as $type) {
            Discounts::addType($type);
        }
    }
}
