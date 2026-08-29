<?php

namespace Modules\Core\Listeners;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Modules\Core\Models\ScheduledRun;
use Throwable;

/**
 * Records every scheduled-task run.
 *
 * Hooked onto Laravel's own scheduler events rather than `->before()`/`->after()`
 * on each task, so every command in routes/console.php is covered the moment it
 * is added — nobody has to remember to wire up monitoring for a new one.
 *
 * Writing must never be able to break the task it is observing: a failure here
 * is swallowed and reported to the log, not raised.
 */
class RecordScheduledRun
{
    /** @var array<string, int> command => scheduled_runs.id */
    private array $open = [];

    public function starting(ScheduledTaskStarting $event): void
    {
        $command = $this->name($event->task);

        $this->guard(function () use ($command) {
            $this->open[$command] = ScheduledRun::create([
                'command' => $command,
                'started_at' => now(),
            ])->id;
        });
    }

    public function finished(ScheduledTaskFinished $event): void
    {
        $this->close($event->task, ok: true, runtime: $event->runtime);
    }

    public function failed(ScheduledTaskFailed $event): void
    {
        $this->close(
            $event->task,
            ok: false,
            failure: $event->exception?->getMessage(),
        );
    }

    private function close(ScheduledEvent $task, bool $ok, ?float $runtime = null, ?string $failure = null): void
    {
        $command = $this->name($task);

        $this->guard(function () use ($command, $ok, $runtime, $failure) {
            $id = $this->open[$command] ?? null;

            if ($id === null) {
                // No matching start — the row is still worth having, otherwise a
                // task that dies before `starting` fires looks like it never ran.
                ScheduledRun::create([
                    'command' => $command,
                    'started_at' => now(),
                    'finished_at' => now(),
                    'ok' => $ok,
                    'failure' => $failure,
                ]);

                return;
            }

            unset($this->open[$command]);

            ScheduledRun::whereKey($id)->update([
                'finished_at' => now(),
                'runtime_ms' => $runtime === null ? null : (int) round($runtime * 1000),
                'ok' => $ok,
                'failure' => $failure,
            ]);
        });
    }

    /**
     * A stable identity for the task. Laravel's `$task->command` carries the PHP
     * binary and artisan path, which differ between machines — strip them so the
     * same job matches across dev, CI and production.
     */
    public function name(ScheduledEvent $task): string
    {
        $description = $task->description ?: $task->command ?: (string) $task->getSummaryForDisplay();

        if (preg_match("/artisan'?\s+(.+)$/", $description, $matches)) {
            return trim($matches[1], " '\"");
        }

        return trim($description);
    }

    private function guard(callable $write): void
    {
        try {
            $write();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
