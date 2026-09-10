<?php

namespace Modules\Assets\Console\Commands;

use Illuminate\Console\Command;
use Modules\Assets\Services\MediaRegenerator;

/**
 * Rebuild image conversions in the background, after the sizes change.
 *
 * Changing a size only affects images generated from then on — the files
 * already on disk keep the pixels they were cut to. This is what brings the
 * existing library up to the new numbers.
 *
 * Spatie ships `media-library:regenerate`, and for a small library it is the
 * simpler choice. This one exists for the case that command handles badly: it
 * works synchronously in one process, so a few thousand images either take the
 * whole terminal for a long time or hit a limit. `MediaRegenerator` batches the
 * work onto the `media` queue instead, which is where the conversion workers
 * already are.
 *
 * Needs a queue worker running, and says so rather than appearing to hang.
 */
class RegenerateConversions extends Command
{
    protected $signature = 'media:regenerate {--missing : Chỉ sinh những conversion đang thiếu file}';

    protected $description = 'Xếp hàng đợi tạo lại conversion ảnh (dùng sau khi đổi kích thước)';

    public function handle(MediaRegenerator $regenerator): int
    {
        $onlyMissing = (bool) $this->option('missing');

        $batchId = $regenerator->dispatch($onlyMissing);

        if ($batchId === null) {
            $this->components->warn('Không có media nào để xử lý.');

            return self::SUCCESS;
        }

        $this->components->info(
            ($onlyMissing ? 'Đã xếp hàng đợi sinh conversion còn thiếu' : 'Đã xếp hàng đợi tạo lại toàn bộ conversion')
            ." (batch {$batchId})."
        );

        if (! $regenerator->workerAvailable()) {
            // Queued work with nothing draining the queue looks exactly like
            // success until someone notices the images never changed.
            $this->components->warn(
                'Chưa có worker nào chạy hàng đợi `media` — batch sẽ nằm im. '
                .'Khởi động Horizon (`php artisan horizon`) hoặc `php artisan queue:work --queue=media`.'
            );
        }

        return self::SUCCESS;
    }
}
