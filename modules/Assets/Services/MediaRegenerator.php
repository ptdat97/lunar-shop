<?php

namespace Modules\Assets\Services;

use Modules\Core\Support\Queues;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Modules\Assets\Jobs\RegenerateConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Dispatches conversion regeneration as a queued batch so a large media library
 * is rebuilt across many small jobs (cancellable, with progress) instead of one
 * synchronous request. The admin page polls {@see self::progress()} to show a
 * live progress bar; the "current batch" id is remembered so progress survives a
 * page refresh.
 */
class MediaRegenerator
{
    /** Media items per job — keeps each job short and memory-bounded. */
    protected const CHUNK = 25;

    /** Cache key holding the id of the most recently dispatched batch. */
    protected const CURRENT_BATCH_KEY = 'media.regenerate.batch';

    /**
     * Queue a batch that regenerates conversions for all media.
     *
     * @param  bool  $onlyMissing  only (re)generate conversions whose file is missing
     * @return string|null  the batch id, or null when there's no media to process
     */
    public function dispatch(bool $onlyMissing = false): ?string
    {
        $ids = Media::query()->orderBy('id')->pluck('id');

        if ($ids->isEmpty()) {
            return null;
        }

        $jobs = $ids
            ->chunk(self::CHUNK)
            ->map(fn ($chunk) => new RegenerateConversionsJob($chunk->values()->all(), $onlyMissing))
            ->all();

        $batch = Bus::batch($jobs)
            ->name('media:regenerate')
            ->onQueue(Queues::MEDIA) // heavy conversions run on the media supervisor
            ->allowFailures()
            ->finally(function ($batch) {
                Cache::forget(self::CURRENT_BATCH_KEY);
                Cache::forget(self::BATCH_STARTED_KEY . $batch->id);
            })
            ->dispatch();

        Cache::put(self::CURRENT_BATCH_KEY, $batch->id, now()->addDay());
        // Start time for throughput/ETA (jobs process CHUNK images each).
        Cache::put(self::BATCH_STARTED_KEY . $batch->id, now()->getTimestamp(), now()->addDay());

        return $batch->id;
    }

    /** Cache key prefix holding a batch's start timestamp (for ETA/throughput). */
    protected const BATCH_STARTED_KEY = 'media.regenerate.started.';

    /**
     * Progress of a batch (defaults to the current one). Shape is UI-ready and
     * Horizon-aware: it reports whether a worker is available to drain the queue
     * (so the UI can warn on a stalled batch) plus throughput + ETA.
     *
     * @return array{id:?string, total:int, pending:int, processed:int, failed:int, progress:int, finished:bool, cancelled:bool, worker_available:bool, stalled:bool, per_min:?int, eta_seconds:?int}|null
     */
    public function progress(?string $batchId = null): ?array
    {
        $batchId ??= Cache::get(self::CURRENT_BATCH_KEY);

        if (! $batchId) {
            return null;
        }

        $batch = $this->find($batchId);

        if (! $batch) {
            return null;
        }

        $running = ! $batch->finished() && ! $batch->cancelled();
        $workerAvailable = $this->workerAvailable();

        // Throughput/ETA from the batch's start time (jobs = CHUNK images each).
        $perMin = null;
        $etaSeconds = null;
        $startedAt = Cache::get(self::BATCH_STARTED_KEY . $batch->id);
        $processed = $batch->processedJobs();

        if ($startedAt && $processed > 0) {
            $elapsed = max(1, now()->getTimestamp() - (int) $startedAt);
            $perMin = (int) round($processed / $elapsed * 60);
            if ($running && $perMin > 0 && $batch->pendingJobs > 0) {
                $etaSeconds = (int) ceil($batch->pendingJobs / max(1, $processed / $elapsed));
            }
        }

        return [
            'id' => $batch->id,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'processed' => $processed,
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'worker_available' => $workerAvailable,
            // Stalled = still running, nothing processed yet, and no worker up.
            'stalled' => $running && $processed === 0 && ! $workerAvailable,
            'per_min' => $perMin,
            'eta_seconds' => $etaSeconds,
        ];
    }

    /**
     * Cancel the current (or given) batch.
     */
    public function cancel(?string $batchId = null): void
    {
        $batchId ??= Cache::get(self::CURRENT_BATCH_KEY);

        $this->find($batchId)?->cancel();
        Cache::forget(self::CURRENT_BATCH_KEY);

        if ($batchId) {
            Cache::forget(self::BATCH_STARTED_KEY . $batchId);
        }
    }

    /**
     * Whether a batch is currently running.
     */
    public function isRunning(): bool
    {
        $progress = $this->progress();

        return $progress !== null && ! $progress['finished'] && ! $progress['cancelled'];
    }

    protected function find(?string $batchId): ?Batch
    {
        if (! $batchId) {
            return null;
        }

        try {
            return Bus::findBatch($batchId);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Whether a queue worker is available to drain the `media` queue. Uses
     * Horizon's master-supervisor status when Horizon is installed (the way this
     * app runs workers in prod); otherwise assumes true so a plain
     * `queue:work`/`sync` setup isn't wrongly flagged as stalled.
     *
     * The UI uses this to warn "batch queued but no worker running" instead of
     * showing a progress bar that never moves.
     */
    public function workerAvailable(): bool
    {
        if (! interface_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
            return true; // no Horizon → can't tell; don't cry wolf.
        }

        try {
            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

            foreach ($masters as $master) {
                // A running master reports 'running'/'paused'; anything present
                // means the daemon is up and will drain the queue.
                if (($master->status ?? null) !== null) {
                    return true;
                }
            }
        } catch (Throwable $e) {
            return true; // Horizon repo unreachable → don't block the UI.
        }

        return false;
    }
}
