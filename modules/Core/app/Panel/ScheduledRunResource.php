<?php

namespace Modules\Core\Panel;

use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\ScheduledRun;

/**
 * What the scheduler has actually been doing.
 *
 * The rows were already being written on every tick, but the only way to read
 * them was `schedule:heartbeat` over SSH. "Did the cron run last night, and did
 * anything fail?" is a question the person running the shop asks, and it should
 * not require a terminal.
 *
 * A log, so nothing here creates or edits. Deleting stays available for pruning
 * a table that grows on every tick forever.
 */
class ScheduledRunResource extends PanelResource
{
    public function model(): string
    {
        return ScheduledRun::class;
    }

    public function section(): string
    {
        return 'shop';
    }

    /** Nhật ký scheduler: chỉ đọc, chỉ mở khi đi truy sự cố. Trước đây nó đứng ĐẦU sidebar. */
    public function navigationGroup(): string
    {
        return 'shop-system';
    }

    public function navigationPriority(): int
    {
        return 10;
    }

    public function key(): string
    {
        return 'scheduled-runs';
    }

    public function label(): string
    {
        return __('admin.scheduled_runs.plural');
    }

    public function singular(): string
    {
        return __('admin.scheduled_runs.label');
    }

    public function icon(): string
    {
        return 'clock';
    }

    public function permission(): string
    {
        return 'settings:core';
    }

    public function canCreate(): bool
    {
        return false;
    }

    public function canEdit(): bool
    {
        return false;
    }

    public function fields(): array
    {
        return [
            Field::text('command', __('admin.scheduled_runs.command'))->onIndex(),
            Field::toggle('ok', __('admin.scheduled_runs.ok'))->onIndex()->width(2),
            Field::textarea('failure', __('admin.scheduled_runs.failure')),
        ];
    }

    /**
     * Failures first within the newest runs, because a green wall of successes
     * is exactly where a single red row hides.
     */
    public function indexQuery(Builder $query): Builder
    {
        return $query->orderBy('ok');
    }

    public function computed(): array
    {
        return [
            'ran_at' => fn (ScheduledRun $run) => $run->started_at?->format('d/m/Y H:i:s'),
            'runtime' => fn (ScheduledRun $run) => $run->runtime_ms === null
                ? null
                : ($run->runtime_ms < 1000
                    ? $run->runtime_ms.' ms'
                    : round($run->runtime_ms / 1000, 1).' s'),
        ];
    }

    public function computedLabels(): array
    {
        return [
            'ran_at' => __('admin.scheduled_runs.ran_at'),
            'runtime' => __('admin.scheduled_runs.runtime'),
        ];
    }

    public function searchable(): array
    {
        return ['command', 'failure'];
    }

    public function defaultSort(): array
    {
        return ['started_at', 'desc'];
    }
}
