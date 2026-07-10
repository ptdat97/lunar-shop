<?php

namespace Modules\Notification\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Support\AdminPages;
use Modules\Notification\Contracts\PushSender;
use Modules\Notification\Drivers\NullPushSender;
use Modules\Notification\Filament\Pages\NotificationSettingsPage;
use Modules\Notification\Listeners\SendOrderStatusNotification;
use Modules\Order\Events\OrderStatusUpdated;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/notification.php', 'notification');

        // Provider-agnostic push, resolved from config — adding FCM later is a
        // config change, not a refactor (same shape as Catalog's SearchEngine).
        $this->app->singleton(PushSender::class, function ($app) {
            $driver = config('notification.push.driver', 'null');

            return $app->make(config("notification.push.drivers.{$driver}", NullPushSender::class));
        });

        AdminPages::add(NotificationSettingsPage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'notification');

        // A second consumer of the order lifecycle: the app needs to hear about
        // every transition, whereas the status email keeps its own skip rules.
        Event::listen(OrderStatusUpdated::class, SendOrderStatusNotification::class);
    }
}
