<?php

namespace Modules\Payment\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Facades\Payments;
use Modules\Payment\PaymentTypes\VNPayPayment;

class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(base_path('config/payment.php'), 'payment');

        // Resolve the VNPay services from config so controllers can inject them.
        $this->app->bind(\Modules\Payment\Services\VNPayGateway::class,
            fn () => \Modules\Payment\Services\VNPayGateway::fromConfig());
    }

    /**
     * Bootstrap module: routes, migrations, payment drivers.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Register the VNPay driver so config('lunar.payments.types.vnpay')
        // resolves to it. Checkout already dispatches via Payments::driver(...).
        Payments::extend('vnpay', fn ($app) => $app->make(VNPayPayment::class));
    }
}
