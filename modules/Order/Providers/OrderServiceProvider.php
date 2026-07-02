<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Events\PaymentAttemptEvent;
use Lunar\Models\Order;
use Modules\Order\Events\OrderPaid;
use Modules\Order\Listeners\SendOrderConfirmation;
use Modules\Order\Listeners\SendOrderPaidEmail;
use Modules\Order\Observers\OrderObserver;
use Modules\Core\Support\AdminPages;

class OrderServiceProvider extends ServiceProvider
{
    /**
     * Register module bindings.
     */
    public function register(): void
    {
        // Admin resource for customer return (RMA) requests (Sales group).
        AdminPages::addResource(\Modules\Order\Filament\Resources\ReturnRequestResource::class);
    }

    /**
     * Bootstrap module: routes, migrations, views, order emails.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');

        // Transactional email templates under the order:: namespace (separate
        // from the storefront theme — these aren't theme views).
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'order');

        // Email wiring (queued mailables; MAIL_MAILER drives transport).
        Event::listen(PaymentAttemptEvent::class, SendOrderConfirmation::class);
        Event::listen(OrderPaid::class, SendOrderPaidEmail::class);

        // Status-change email (e.g. dispatched/completed) via model observer.
        Order::observe(OrderObserver::class);
    }
}
