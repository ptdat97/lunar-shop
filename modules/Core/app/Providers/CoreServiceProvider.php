<?php

namespace Modules\Core\Providers;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Console\ReportUntranslatedContent;
use Modules\Core\Console\ScheduleHeartbeat;
use Lunar\Panel\Facades\Panel;
use Modules\Core\Listeners\RecordScheduledRun;
use Modules\Core\Panel\ResourceRegistry;
use Modules\Core\Panel\SettingsRegistry;
use Modules\Core\Panel\ShopSection;
use Modules\Core\Support\Settings;

/**
 * Core module: shared, cross-cutting infrastructure only (settings store, queue
 * names, admin-page collector, Lunar config override helper). No business logic
 * lives here — it's the base layer every feature module builds on. Registered
 * first so its bindings are available to all other module providers.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin-configurable feature settings (payment/shipping/membership/…),
        // DB-backed with config/env fallback. Scoped (one instance per request,
        // Octane-safe) so its in-object memo makes the cache-store read happen
        // at most once per request.
        $this->app->scoped(Settings::class);

        // Singleton: the scheduler-event listener keeps the open run rows in
        // memory between the starting and finished events of one tick.
        $this->app->singleton(RecordScheduledRun::class);

        // Singleton so every module's provider adds to the same registry; the
        // panel reads it once, when it processes sections after boot.
        $this->app->singleton(ResourceRegistry::class);

        // Same reason: every module adds its settings groups to one registry,
        // which the panel reads once sections are processed.
        $this->app->singleton(SettingsRegistry::class);
    }

    public function boot(): void
    {
        // The shared app_settings + scheduled_runs tables live with the Core
        // infrastructure.
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $this->commands([ScheduleHeartbeat::class, ReportUntranslatedContent::class]);

        // One listener instance across all three events so a task's start row can
        // be matched to its finish. Hooking the scheduler's own events (rather
        // than ->before()/->after() per task) means any command added to
        // routes/console.php is monitored without further wiring.
        $recorder = $this->app->make(RecordScheduledRun::class);

        Event::listen(ScheduledTaskStarting::class, fn ($event) => $recorder->starting($event));
        Event::listen(ScheduledTaskFinished::class, fn ($event) => $recorder->finished($event));
        Event::listen(ScheduledTaskFailed::class, fn ($event) => $recorder->failed($event));

        // The shop's section on the Lunar panel. Registered from boot because
        // PanelManager warns (and ignores the section) once it has processed
        // sections, which happens after every provider has booted — feature
        // modules add their resources to the registry in their own boot.
        Panel::section($this->app->make(ShopSection::class));
    }
}
