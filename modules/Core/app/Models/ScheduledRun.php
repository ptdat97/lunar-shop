<?php

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Listeners\RecordScheduledRun;

/**
 * A single execution of a scheduled task. Written by
 * {@see RecordScheduledRun}, read by
 * `schedule:heartbeat`.
 */
class ScheduledRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'command',
        'started_at',
        'finished_at',
        'runtime_ms',
        'ok',
        'failure',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'ok' => 'bool',
    ];
}
