<?php

namespace Modules\Notification\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Support\AdminPages;
use Modules\Notification\Contracts\PushSender;
use Modules\Notification\Contracts\SmsSender;
use Modules\Notification\Drivers\NullPushSender;
use Modules\Notification\Drivers\NullSmsSender;
use Modules\Notification\Filament\Pages\NotificationSettingsPage;
use Modules\Notification\Listeners\SendOrderStatusNotification;
use Modules\Notification\Listeners\SendOrderStatusSms;
use Modules\Notification\Support\MailSettings;
use Modules\Order\Events\OrderStatusUpdated;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/notification.php', 'notification');

        // Provider-agnostic push, resolved from config — adding FCM later is a
        // config change, not a refactor (same shape as Catalog's SearchEngine).
        $this->app->singleton(PushSender::class, function ($app) {
            $driver = config('notification.push.driver', 'null');

            return $app->make(config("notification.push.drivers.{$driver}", NullPushSender::class));
        });

        $this->app->singleton(SmsSender::class, function ($app) {
            $driver = config('notification.sms.driver', 'null');

            return $app->make(config("notification.sms.drivers.{$driver}", NullSmsSender::class));
        });

        AdminPages::add(NotificationSettingsPage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'notification');

        $this->applyMailSettings();

        // A second consumer of the order lifecycle: the app needs to hear about
        // every transition, whereas the status email keeps its own skip rules.
        Event::listen(OrderStatusUpdated::class, SendOrderStatusNotification::class);

        // Queued, and separate, so a slow or failing SMS gateway cannot delay
        // the request or block the push/in-app notification above.
        Event::listen(OrderStatusUpdated::class, SendOrderStatusSms::class);
    }

    /**
     * Push admin-configured SMTP credentials into the runtime mail config.
     *
     * Deferred to the moment the mail manager is first resolved rather than run
     * here, for two reasons. Reading settings hits the database, which is not
     * safe during `package:discover` or a `migrate` on an empty schema — boot
     * runs there, resolving a mailer does not. And every order mail is queued,
     * so the send happens inside a worker: applying at boot only would leave the
     * admin's SMTP host configured in the web process and ignored in the one
     * that actually sends.
     *
     * `resolving()` fires before the manager is constructed, so the config is in
     * place by the time it reads it. A failure must never make the app
     * unbootable or silently swallow the mail, hence report-and-continue: the
     * .env configuration stays in force.
     */
    protected function applyMailSettings(): void
    {
        $this->app->resolving('mail.manager', function () {
            try {
                MailSettings::apply();
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
