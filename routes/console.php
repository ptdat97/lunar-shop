<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production schedule — requires one cron entry on the server:
//   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1

// Horizon dashboard metrics (throughput/wait-time graphs) are built from
// periodic snapshots; without this the graphs stay empty.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Expired Sanctum personal access tokens (app/headless clients) pile up in
// personal_access_tokens — prune once expired for over 24h.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Failed queue jobs older than a week have been investigated or never will be.
Schedule::command('queue:prune-failed --hours=168')->weekly();
