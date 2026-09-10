<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Core\Contracts\Actions\Orders\NotifiesCustomer;
use Lunar\Core\Events\Orders\OrderCancelled;
use Lunar\Core\Events\Orders\OrderClosed;
use Lunar\Core\Events\Orders\OrderFulfilmentStatusUpdated;
use Lunar\Core\Events\Orders\OrderPaymentStatusUpdated;
use Lunar\Core\Events\Orders\OrderReopened;
use Lunar\Core\Events\PaymentAttemptEvent;
use Lunar\Core\Facades\OrderNotifications;
use Lunar\Core\Models\Order;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Order\Actions\NotifyCustomerWithUserFallback;
use Modules\Order\Console\RequestReviews;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Events\OrderStatusUpdated;
use Modules\Order\Listeners\DispatchOrderPaidForOfflineOrder;
use Modules\Order\Listeners\RaiseOrderStatusUpdated;
use Modules\Order\Listeners\RecordOrderStatusHistory;
use Modules\Order\Listeners\SendOrderConfirmation;
use Modules\Order\Listeners\SendOrderPaidEmail;
use Modules\Order\Listeners\SendOrderStatusEmail;
use Modules\Order\Notifications\OrderConfirmationNotification;
use Modules\Order\Notifications\OrderPaidNotification;
use Modules\Order\Observers\OrderObserver;
use Modules\Order\Panel\ReturnRequestResource;

class OrderServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // See the class docblock: Lunar's recipient rule is one fallback short
        // of the one every automatic order email already uses.
        $this->app->bind(NotifiesCustomer::class, NotifyCustomerWithUserFallback::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, order emails.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');

        // The returns queue on the panel — the one admin screen this module
        // owns; orders themselves are Lunar's first-party screen now.
        $this->app->make(ResourceRegistry::class)->add(new ReturnRequestResource);

        // Transactional email templates under the order:: namespace (separate
        // from the storefront theme — these aren't theme views).
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'order');

        // The panel's "notify customer" action builds whatever it finds in
        // Lunar's catalogue, which ships with only a generic `order-update`.
        // Register the shop's own order emails so staff can resend the one the
        // customer is actually asking about. OrderStatusUpdatedMail is left out
        // on purpose: it renders a transition (`previousStatus` → current), so
        // there is no such thing as resending it on its own.
        // The KEY, not `__()` of it: the manifest translates on read, so
        // translating here would freeze whichever locale happened to be active
        // when the container booted — the staff member's, not the shop's.
        OrderNotifications::register(
            'order-confirmation',
            OrderConfirmationNotification::class,
            'order.notifications.confirmation',
        )->register(
            'order-paid',
            OrderPaidNotification::class,
            'order.notifications.paid',
        );

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

        $this->commands([RequestReviews::class]);
    }
}
