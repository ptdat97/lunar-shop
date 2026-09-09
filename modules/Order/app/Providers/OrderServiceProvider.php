<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Events\Orders\OrderCancelled;
use Lunar\Core\Events\Orders\OrderClosed;
use Lunar\Core\Events\Orders\OrderFulfilmentStatusUpdated;
use Lunar\Core\Events\Orders\OrderPaymentStatusUpdated;
use Lunar\Core\Events\Orders\OrderReopened;
use Lunar\Core\Events\PaymentAttemptEvent;
use Lunar\Core\Models\Order;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Listeners\DispatchOrderPaidForOfflineOrder;
use Modules\Order\Listeners\RaiseOrderStatusUpdated;
use Modules\Order\Listeners\RecordOrderStatusHistory;
use Modules\Order\Listeners\SendOrderConfirmation;
use Modules\Order\Listeners\SendOrderPaidEmail;
use Modules\Order\Listeners\SendOrderStatusEmail;
use Modules\Order\Observers\OrderObserver;

class OrderServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void {}

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

        // The shop's lifecycle status is derived from four facts since Lunar
        // 2.0 (see OrderStatus), and the two rollups behind it are written with
        // saveQuietly() — an observer would never see them. So the domain event
        // is raised from Lunar's own lifecycle events instead.
        Event::listen(OrderPaymentStatusUpdated::class, [RaiseOrderStatusUpdated::class, 'handlePaymentStatus']);
        Event::listen(OrderFulfilmentStatusUpdated::class, [RaiseOrderStatusUpdated::class, 'handleFulfilmentStatus']);
        Event::listen(OrderCancelled::class, [RaiseOrderStatusUpdated::class, 'handleCancelled']);
        Event::listen(OrderClosed::class, [RaiseOrderStatusUpdated::class, 'handleClosed']);
        Event::listen(OrderReopened::class, [RaiseOrderStatusUpdated::class, 'handleReopened']);

        // Consumers of that event: the customer's email, and the history the
        // order timeline reads (2.0 stopped logging status changes, having no
        // status column left to log).
        Event::listen(OrderStatusUpdated::class, SendOrderStatusEmail::class);
        Event::listen(OrderStatusUpdated::class, RecordOrderStatusHistory::class);

        // Placement is the one transition that is not one of those events: it
        // moves the order from nothing to awaiting-payment / payment-offline by
        // writing `placed_at` and `meta.payment_type` on an ordinary save.
        Order::observe(OrderObserver::class);
    }
}
