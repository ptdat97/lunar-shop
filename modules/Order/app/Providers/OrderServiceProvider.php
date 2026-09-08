<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Events\PaymentAttemptEvent;
use Lunar\Core\Models\Order;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Listeners\DispatchOrderPaidForOfflineOrder;
use Modules\Order\Listeners\SendOrderConfirmation;
use Modules\Order\Listeners\SendOrderPaidEmail;
use Modules\Order\Observers\OrderObserver;

class OrderServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap module: routes, migrations, views, order emails.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // Transactional email templates under the order:: namespace (separate
        // from the storefront theme — these aren't theme views).
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'order');

        // Email wiring (queued mailables; MAIL_MAILER drives transport).
        Event::listen(PaymentAttemptEvent::class, SendOrderConfirmation::class);
        Event::listen(OrderPaid::class, SendOrderPaidEmail::class);

        // COD is paid the moment it's placed (`payment-offline`), but Lunar's
        // offline driver has no "paid" signal — raise our domain event so
        // membership/analytics consumers see COD orders too.
        Event::listen(PaymentAttemptEvent::class, DispatchOrderPaidForOfflineOrder::class);

        // Status-change email (e.g. dispatched/completed) via model observer.
        Order::observe(OrderObserver::class);
    }
}
