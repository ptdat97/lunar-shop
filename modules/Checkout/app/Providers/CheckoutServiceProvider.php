<?php

namespace Modules\Checkout\Providers;

use Illuminate\Support\ServiceProvider;
use Lunar\Base\CartSessionInterface;
use Lunar\Facades\Payments;
use Lunar\Managers\CartSessionManager;
use Modules\Checkout\Filament\Pages\PaymentSettingsPage;
use Modules\Checkout\PaymentTypes\MoMoPayment;
use Modules\Checkout\PaymentTypes\VNPayPayment;
use Modules\Checkout\Services\CartService;
use Modules\Checkout\Services\MoMoGateway;
use Modules\Checkout\Services\TokenAwareCartSession;
use Modules\Checkout\Services\VNPayGateway;
use Modules\Core\Support\AdminPages;
use Modules\Core\Support\LunarConfigOverride;

class CheckoutServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Cart state is server-side (Lunar CartSession); the service is a
        // singleton so one instance serves the request.
        $this->app->singleton(CartService::class);

        $this->mergeConfigFrom(base_path('config/payment.php'), 'payment');

        // Resolve the VNPay + MoMo gateways from config so controllers can inject them.
        $this->app->bind(VNPayGateway::class, fn () => VNPayGateway::fromConfig());
        $this->app->bind(MoMoGateway::class, fn () => MoMoGateway::fromConfig());

        // Admin page to configure gateway keys (VNPay + MoMo) without editing .env.
        AdminPages::add(PaymentSettingsPage::class);
    }

    /**
     * Bootstrap module: config overrides, routes, payment drivers.
     */
    public function boot(): void
    {
        $this->registerCartSession();

        // Re-apply cart_session override (auto_create) on top of Lunar's
        // published config/lunar/cart_session.php — survives --force.
        LunarConfigOverride::applyFrom('lunar.cart_session', __DIR__.'/../../config/cart-overrides.php');

        // Re-apply payment-type overrides (COD default, bank-transfer, vnpay) on
        // top of Lunar's published config/lunar/payments.php — survives --force.
        LunarConfigOverride::applyFrom('lunar.payments', __DIR__.'/../../config/payment-overrides.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'checkout-admin');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Register the VNPay + MoMo drivers so config('lunar.payments.types.*')
        // resolves to them. Checkout dispatches via Payments::driver(...).
        Payments::extend('vnpay', fn ($app) => $app->make(VNPayPayment::class));
        Payments::extend('momo', fn ($app) => $app->make(MoMoPayment::class));
    }

    /**
     * Swap Lunar's session-only cart resolver for one that also understands
     * Bearer tokens, so cart + checkout work headlessly (mobile app, POS).
     *
     * Lunar exposes this as a container singleton (`LunarServiceProvider`), so
     * this is a supported extension point — no vendor edit. Bound in boot()
     * because Lunar registers via package discovery and its register() may run
     * after this module's; rebinding here is unambiguous.
     *
     * Both keys are rebound: the `CartSession` facade resolves the interface,
     * while some Lunar internals type-hint the concrete manager.
     */
    protected function registerCartSession(): void
    {
        $this->app->singleton(TokenAwareCartSession::class);
        $this->app->singleton(CartSessionInterface::class, fn ($app) => $app->make(TokenAwareCartSession::class));
        $this->app->singleton(CartSessionManager::class, fn ($app) => $app->make(TokenAwareCartSession::class));
    }
}
