<?php

namespace Modules\Pricing\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Pricing\Services\PricingService;

class PricingServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap module: routes, migrations, views.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        $this->composeThemePrices();
    }

    /**
     * Inject formatted prices into theme views so Blade never resolves the
     * pricing service itself (coding standards §7).
     *  - price component → $formatted (first variant display price)
     *  - product page     → $displayPrice / $lowestPriceAmount / $currencyCode
     */
    protected function composeThemePrices(): void
    {
        $pricing = fn () => $this->app->make(PricingService::class);

        View::composer('theme::components.price', function ($view) use ($pricing): void {
            $product = $view->getData()['product'] ?? null;
            $view->with('formatted', $product ? $pricing()->displayPrice($product) : null);
        });

        View::composer('theme::pages.product', function ($view) use ($pricing): void {
            $svc = $pricing();
            $product = $view->getData()['product'] ?? null;
            $view->with([
                'displayPrice' => $product ? $svc->displayPrice($product) : null,
                'lowestPriceAmount' => $product ? $svc->lowestPriceAmount($product) : null,
                'currencyCode' => $svc->defaultCurrencyCode(),
            ]);
        });
    }
}
