<?php

namespace Modules\Assets\Services;

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
            ->allowFailures()
            ->finally(fn () => Cache::forget(self::CURRENT_BATCH_KEY))
            ->dispatch();

        Cache::put(self::CURRENT_BATCH_KEY, $batch->id, now()->addDay());

        return $batch->id;
    }

    /**
     * Progress of a batch (defaults to the current one). Shape is UI-ready.
     *
     * @return array{id:?string, total:int, pending:int, processed:int, failed:int, progress:int, finished:bool, cancelled:bool}|null
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

        return [
            'id' => $batch->id,
            'total' => $batch->totalJobs,
            'pending' => $batch->pendingJobs,
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
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
}
