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

// A gateway order reserves its stock the moment it is created — before the
// customer reaches VNPay/MoMo. Someone who closes the tab would hold those units
// forever, so cancel unpaid gateway orders and put the stock back. Bank transfers
// sit in the same status but are settled by hand; the command leaves them alone.
//
// No --minutes here: how long to hold stock is a shop decision, set in
// Admin → Settings → Inventory. Passing it would silently outrank that page.
Schedule::command('orders:expire-abandoned')
    ->everyTenMinutes()
    ->withoutOverlapping();

// Dây bảo hiểm: báo động khi một scheduled task ngừng chạy hoặc liên tục lỗi.
// Ngưỡng lấy từ chính cron expression của từng task nên thêm command mới là tự
// được canh, không phải sửa gì ở đây.
//
// Lưu ý giới hạn: nếu CẢ scheduler chết thì lệnh này cũng không chạy, nên nó chỉ
// bắt được trường hợp một task lặng đi trong khi cron vẫn sống. Muốn phủ nốt
// trường hợp còn lại thì cần một uptime check bên ngoài gọi
// `php artisan schedule:heartbeat` và cảnh báo theo exit code (xem deployment.md §4).
Schedule::command('schedule:heartbeat --quiet-ok')
    ->hourly()
    ->withoutOverlapping();
