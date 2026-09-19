<?php

namespace Modules\Assets\Console\Commands;

use Illuminate\Console\Command;
use Modules\Assets\Services\LibraryLinks;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Move the galleries' existing images into the library, once.
 *
 * Before LibraryLinks, every image uploaded to a product, collection, brand,
 * product type or swatch owned its file. This turns each of those rows into a
 * link: its original moves into the library as a new file — or is dropped when
 * the library already holds the same bytes — and the row keeps its id, order,
 * primary flag, alt/caption and variant-pivot entries. Renditions stay put;
 * they were made from the same picture.
 *
 * Idempotent: links are skipped, so a second run (or a run after a partial
 * one) only touches what is left. `--dry-run` reports without writing.
 */
class AdoptGalleryMedia extends Command
{
    protected $signature = 'assets:adopt-galleries {--dry-run : Report what would change without writing}';

    protected $description = 'Move existing gallery images (products, collections, brands, product types, swatches) into the media library';

    public function handle(LibraryLinks $links): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $types = collect(array_keys(LibraryLinks::MODELS))
            ->map(fn (string $class) => (new $class)->getMorphClass())
            ->all();

        $tally = ['moved' => 0, 'reused' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];
        $planned = [];

        Media::query()
            ->whereIn('model_type', $types)
            ->whereNull('custom_properties->'.LibraryLinks::SOURCE)
            ->with('model')
            ->chunkById(100, function ($chunk) use ($links, $dryRun, &$tally, &$planned): void {
                foreach ($chunk as $media) {
                    try {
                        $tally[$links->adopt($media, $dryRun, $planned)]++;
                    } catch (Throwable $e) {
                        $tally['failed']++;
                        $this->warn("  media #{$media->id} ({$media->file_name}): {$e->getMessage()}");
                    }
                }
            });

        $this->table(
            ['', $dryRun ? 'would' : 'done'],
            [
                ['moved into the library', $tally['moved']],
                ['linked to an identical library file', $tally['reused']],
                ['left as is (not an image the library takes)', $tally['skipped']],
                ['original missing on disk', $tally['missing']],
                ['failed', $tally['failed']],
            ],
        );

        if ($dryRun) {
            $this->info('Dry run — nothing was written. Run without --dry-run to apply.');
        }

        return $tally['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
