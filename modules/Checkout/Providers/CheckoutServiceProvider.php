<?php

namespace Modules\Checkout\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Facades\Payments;
use Modules\Checkout\PaymentTypes\VNPayPayment;
use Modules\Checkout\Services\CartService;
use Modules\Checkout\Services\VNPayGateway;
use Modules\Theme\Support\LunarConfigOverride;

class CheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cart state is server-side (Lunar CartSession); the service is a
        // singleton so one instance serves the request.
        $this->app->singleton(CartService::class);

        $this->mergeConfigFrom(base_path('config/payment.php'), 'payment');

        // Resolve the VNPay gateway from config so controllers can inject it.
        $this->app->bind(VNPayGateway::class, fn () => VNPayGateway::fromConfig());
    }

    /**
     * Bootstrap module: config overrides, routes, payment drivers.
     */
    public function boot(): void
    {
        // Re-apply cart_session override (auto_create) on top of Lunar's
        // published config/lunar/cart_session.php — survives --force.
        LunarConfigOverride::applyFrom('lunar.cart_session', __DIR__ . '/../Config/cart-overrides.php');

        // Re-apply payment-type overrides (COD default, bank-transfer, vnpay) on
        // top of Lunar's published config/lunar/payments.php — survives --force.
        LunarConfigOverride::applyFrom('lunar.payments', __DIR__ . '/../Config/payment-overrides.php');

        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Register the VNPay driver so config('lunar.payments.types.vnpay')
        // resolves to it. Checkout dispatches via Payments::driver(...).
        Payments::extend('vnpay', fn ($app) => $app->make(VNPayPayment::class));
    }
}
