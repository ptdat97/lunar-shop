<?php

namespace Modules\Assets\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Assets\Services\ConversionGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Restores media originals from public/demo for rows whose file is missing,
 * then regenerates their conversions.
 *
 * The media table survives a wipe of public/media (that directory is gitignored
 * and never committed), leaving rows that point at files which no longer exist.
 * Conversions cannot be produced from a missing original, so those images 404.
 *
 * Idempotent: rows whose original is already on disk are skipped untouched.
 */
class RestoreMediaFilesSeeder extends Seeder
{
    public function run(): void
    {
        $pool = $this->demoPool();

        if (empty($pool)) {
            $this->command?->warn('Restore: public/demo has no images.');

            return;
        }

        $generator = app(ConversionGenerator::class);
        $restored = 0;
        $skipped = 0;
        $unmatched = [];

        foreach (Media::orderBy('id')->get() as $media) {
            $disk = Storage::disk($media->disk);

            if ($disk->exists($media->getPathRelativeToRoot())) {
                $skipped++;

                continue;
            }

            $source = $pool[$media->file_name] ?? null;

            if (! $source) {
                $unmatched[] = "{$media->id}:{$media->file_name}";

                continue;
            }

            $disk->put($media->getPathRelativeToRoot(), file_get_contents($source));

            $this->regenerate($media, $generator);

            $restored++;
        }

        $this->command?->info("Restore: {$restored} restored, {$skipped} already present.");

        if ($unmatched) {
            $this->command?->warn('Restore: no demo source for '.implode(', ', $unmatched));
        }
    }

    /**
     * Rebuild every conversion for a media item, clearing the cached "exists"
     * flags first — a stale positive would make ensure() skip generation.
     */
    protected function regenerate(Media $media, ConversionGenerator $generator): void
    {
        foreach ($generator->conversionNames($media) as $conversion) {
            $generator->forgetExists($media, $conversion);
            $generator->ensure($media, $conversion);
        }
    }

    /**
     * Demo images keyed by every filename form the media table may hold.
     *
     * Product seeders attach the raw basename ("VNQ00498 copy.jpg"), while
     * MediaLibraryService slugs it ("vnq00498-copy.jpg"), and some rows carry the
     * space-to-hyphen form ("VNQ00498-copy.jpg"). Key all three at the source.
     *
     * @return array<string, string> filename => absolute path
     */
    protected function demoPool(): array
    {
        $pool = [];

        foreach (glob(public_path('demo').'/*.{jpg,jpeg,png}', GLOB_BRACE) ?: [] as $path) {
            $base = basename($path);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $stem = pathinfo($path, PATHINFO_FILENAME);

            $pool[$base] = $path;
            $pool[str_replace(' ', '-', $base)] = $path;
            $pool[Str::slug($stem).'.'.$ext] = $path;
        }

        return $pool;
    }
}
