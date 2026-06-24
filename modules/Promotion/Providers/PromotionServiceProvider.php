<?php

namespace Modules\Promotion\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Lunar\Facades\Discounts;
use Modules\Hook\Facades\Hook;
use Modules\Hook\Support\Hooks;
use Modules\Promotion\Services\MembershipService;
use Modules\Promotion\Services\PromotionService;

class PromotionServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/promotion.php', 'promotion');
    }

    /**
     * Bootstrap module: routes, migrations, views, discount types.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->registerDiscountTypes();
        $this->registerMembershipSync();
        $this->shareFlashSale();

        \Modules\Promotion\Support\PromotionHooks::register();
    }

    /**
     * Expose the current flash sale to the storefront layout (SSR) so the promo
     * bar renders server-side and is crawlable, without the theme depending on
     * this module. Resolved lazily per render of the promo bar partial only.
     */
    protected function shareFlashSale(): void
    {
        View::composer('theme::partials.promo-bar', function ($view): void {
            $promotions = $this->app->make(PromotionService::class);

            $view->with('flashSale', $promotions->currentFlashSale());
        });
    }

    /**
     * Re-evaluate a customer's loyalty tier whenever one of their orders is
     * paid. Listens on the shared `order.paid` hook (fired by the Order module)
     * so we don't couple to its events directly.
     */
    protected function registerMembershipSync(): void
    {
        Hook::addAction(Hooks::ORDER_PAID, function ($order): void {
            $customer = $order?->customer;

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
