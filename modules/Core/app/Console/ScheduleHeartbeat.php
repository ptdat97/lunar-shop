<?php

namespace Modules\Core\Console;

use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Core\Listeners\RecordScheduledRun;
use Modules\Core\Models\ScheduledRun;

/**
 * Dead man's switch for the scheduler.
 *
 * A cron that stops running is silent, and silence is indistinguishable from
 * "there was nothing to do". That matters here more than usual:
 * deployment.md §4 spells out that if `orders:expire-abandoned` stops,
 * stock stays reserved forever and no one finds out.
 *
 * The expected cadence is not configured anywhere — it is read from each task's
 * own cron expression, so a command added to routes/console.php is monitored
 * without touching this file, and changing its schedule cannot leave a stale
 * threshold behind.
 *
 * Tolerance is one whole extra cycle: a task is only overdue once it has missed
 * *two* expected runs. That scales on its own — ~20 minutes for a ten-minute
 * job, ~two days for a daily one — and stops a single slow tick from paging
 * anybody.
 */
class ScheduleHeartbeat extends Command
{
    protected $signature = 'schedule:heartbeat
                            {--quiet-ok : Print nothing when every task is healthy}';

    protected $description = 'Report scheduled tasks that have stopped running or are failing';

    public function handle(Schedule $schedule, RecordScheduledRun $namer): int
    {
        $problems = [];
        $healthy = 0;

        foreach ($schedule->events() as $event) {
            $command = $namer->name($event);
            $deadline = $this->deadline($event);

            if ($deadline === null) {
                continue; // sub-minute or non-cron cadence — nothing to compare against
            }

            $lastOk = ScheduledRun::query()
                ->where('command', $command)
                ->where('ok', true)
                ->max('started_at');

            if ($lastOk === null) {
                $problems[$command] = 'chưa từng chạy thành công';

                continue;
            }

            if (Carbon::parse($lastOk)->lt($deadline)) {
                $problems[$command] = sprintf(
                    'lần chạy thành công gần nhất %s, quá hạn (đáng lẽ phải có sau %s)',
                    Carbon::parse($lastOk)->diffForHumans(),
                    $deadline->toDateTimeString(),
                );

                continue;
            }

            $healthy++;
        }

        if ($problems === []) {
            if (! $this->option('quiet-ok')) {
                $this->info("Scheduler khoẻ — {$healthy} task đều chạy đúng hạn.");
            }

            return self::SUCCESS;
        }

        foreach ($problems as $command => $why) {
            $this->error("{$command}: {$why}");
        }

        // Logged at error level so whatever watches the logs (and Sentry, once
        // it is wired) sees this without needing to poll the command.
        Log::error('Scheduled tasks overdue', $problems);

        // Non-zero on purpose: an external uptime check can call this command
        // and alert on the exit code, which also covers the case where the whole
        // scheduler — this command included — has stopped.
        return self::FAILURE;
    }

    /**
     * The moment a healthy task must have run by: the start of the *previous*
     * expected window, giving one full cycle of slack.
     */
    private function deadline(ScheduledEvent $event): ?Carbon
    {
        $expression = $event->getExpression();

        if (! CronExpression::isValidExpression($expression)) {
            return null;
        }

        $cron = new CronExpression($expression);
        $timezone = $event->timezone ? (string) $event->timezone : config('app.timezone');

        return Carbon::instance(
            $cron->getPreviousRunDate(Carbon::now($timezone), 1, allowCurrentDate: true)
        );
    }
}
