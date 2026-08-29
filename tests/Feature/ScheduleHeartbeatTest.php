<?php

namespace Tests\Feature;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Listeners\RecordScheduledRun;
use Modules\Core\Models\ScheduledRun;
use RuntimeException;
use Tests\TestCase;

/**
 * A cron that stops running is silent, and silence is indistinguishable from
 * "there was nothing to do". deployment.md §4 already warns in writing that if
 * `orders:expire-abandoned` stops, reserved stock is never released — but
 * nothing detected that it had stopped.
 *
 * Mutation check: remove the Event::listen calls from CoreServiceProvider and
 * the recording tests go red; drop the `lt($deadline)` comparison in
 * ScheduleHeartbeat and the overdue test goes red.
 */
class ScheduleHeartbeatTest extends TestCase
{
    /** The task the shop actually depends on for stock to be released. */
    private const CRITICAL = 'orders:expire-abandoned';

    /**
     * @return Collection<int, ScheduledEvent>
     */
    private function scheduledEvents()
    {
        return collect(app(Schedule::class)->events());
    }

    private function eventFor(string $command): ScheduledEvent
    {
        $namer = app(RecordScheduledRun::class);

        $event = $this->scheduledEvents()->first(
            fn (ScheduledEvent $event) => $namer->name($event) === $command,
        );

        $this->assertNotNull($event, "No scheduled task named {$command}.");

        return $event;
    }

    /** Pretend every scheduled task just finished successfully. */
    private function markAllHealthy(): void
    {
        $namer = app(RecordScheduledRun::class);

        foreach ($this->scheduledEvents() as $event) {
            ScheduledRun::create([
                'command' => $namer->name($event),
                'started_at' => now(),
                'finished_at' => now(),
                'ok' => true,
            ]);
        }
    }

    public function test_the_task_the_shop_depends_on_is_actually_scheduled(): void
    {
        $namer = app(RecordScheduledRun::class);

        $this->assertContains(
            self::CRITICAL,
            $this->scheduledEvents()->map(fn ($e) => $namer->name($e))->all(),
            'Nothing releases reserved stock any more — see deployment.md §4.',
        );
    }

    public function test_a_successful_run_is_recorded(): void
    {
        $event = $this->eventFor(self::CRITICAL);

        event(new ScheduledTaskStarting($event));
        event(new ScheduledTaskFinished($event, 1.25));

        $run = ScheduledRun::where('command', self::CRITICAL)->sole();

        $this->assertTrue($run->ok);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(1250, $run->runtime_ms);
        $this->assertNull($run->failure);
    }

    public function test_a_failed_run_is_recorded_with_its_reason(): void
    {
        $event = $this->eventFor(self::CRITICAL);

        event(new ScheduledTaskStarting($event));
        event(new ScheduledTaskFailed($event, new RuntimeException('gateway timeout')));

        $run = ScheduledRun::where('command', self::CRITICAL)->sole();

        $this->assertFalse($run->ok);
        $this->assertStringContainsString('gateway timeout', $run->failure);
    }

    /**
     * The command name must not carry the PHP binary or artisan path, or the
     * same job would look like a different one on every machine.
     */
    public function test_the_recorded_name_is_machine_independent(): void
    {
        $namer = app(RecordScheduledRun::class);

        foreach ($this->scheduledEvents() as $event) {
            $name = $namer->name($event);

            $this->assertStringNotContainsString('artisan', $name);
            $this->assertStringNotContainsString('/', $name);
        }
    }

    public function test_heartbeat_passes_when_every_task_has_run_recently(): void
    {
        $this->markAllHealthy();

        $this->artisan('schedule:heartbeat')->assertExitCode(0);
    }

    public function test_heartbeat_fails_when_a_task_has_never_run(): void
    {
        // Nothing recorded at all — the state a server whose cron was never
        // installed is in.
        $this->artisan('schedule:heartbeat')
            ->expectsOutputToContain(self::CRITICAL)
            ->assertExitCode(1);
    }

    public function test_heartbeat_fails_when_a_task_goes_quiet(): void
    {
        $this->markAllHealthy();

        // orders:expire-abandoned runs every ten minutes; an hour of silence is
        // well past the one-cycle tolerance.
        ScheduledRun::where('command', self::CRITICAL)
            ->update(['started_at' => now()->subHour()]);

        $this->artisan('schedule:heartbeat')
            ->expectsOutputToContain(self::CRITICAL)
            ->assertExitCode(1);
    }

    /** A failing task is not a healthy one, even though it ran. */
    public function test_heartbeat_ignores_runs_that_failed(): void
    {
        $this->markAllHealthy();

        ScheduledRun::where('command', self::CRITICAL)->update(['ok' => false]);

        $this->artisan('schedule:heartbeat')
            ->expectsOutputToContain(self::CRITICAL)
            ->assertExitCode(1);
    }

    /** Recording must never be able to break the task it observes. */
    public function test_a_recording_failure_does_not_bubble_up(): void
    {
        $event = $this->eventFor(self::CRITICAL);

        Schema::drop('scheduled_runs');

        event(new ScheduledTaskStarting($event));
        event(new ScheduledTaskFinished($event, 0.1));

        $this->assertTrue(true, 'Scheduler events must survive a broken monitor table.');
    }
}
