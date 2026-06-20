<?php

namespace Modules\FileManager\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Asset;
use Modules\FileManager\Services\FileManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Seeds the Media Library with demo files (Lunar Assets) sourced from
 * public/demo. Other demo seeders (CMS banners/lookbooks/pages) look
 * these up by media name to wire picker fields, so this must run first.
 *
 * Idempotent: keyed by the media `name`; existing assets are reused.
 */
class MediaLibraryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(FileManager::class);

        foreach ($this->files() as $entry) {
            [$relative, $folder, $name, $alt] = $entry;

            if ($this->existing($name)) {
                continue;
            }

            $path = public_path($relative);

            if (! is_file($path)) {
                $this->command?->warn("Media seed: missing {$relative}");

                continue;
            }

            $manager->storeFromPath($path, $folder, [
                'name' => $name,
                'alt' => $alt,
                'title' => $name,
            ]);
        }
    }

    /**
     * Find an asset whose media already has the given name.
     */
    protected function existing(string $name): ?Asset
    {
        $media = Media::query()->where('name', $name)->first();

        return $media?->model instanceof Asset ? $media->model : null;
    }

    /**
     * Demo files: [relative public path, folder, media name, alt text].
     * Images are drawn from public/demo by index (stable, sorted) — the asset
     * NAMES are what CMS seeders look up, so they stay fixed regardless of which
     * demo filename backs them.
     *
     * @return array<int, array{0:string,1:string,2:string,3:string}>
     */
    protected function files(): array
    {
        $pool = $this->demoImages();

        if (empty($pool)) {
            return [];
        }

        // [folder, media name, alt text] — paired with a demo image by order.
        $entries = [
            // Banners / hero
            ['banners', 'Discover Women', 'Women new season banner'],
            ['banners', 'Discover Men', 'Men new season banner'],
            ['banners', 'Activewear Slider 1', 'Activewear hero slide'],
            ['banners', 'Activewear Slider 2', 'Activewear hero slide'],

            // Lookbook covers
            ['lookbooks', 'Lookbook Spring', 'Spring lookbook cover'],
            ['lookbooks', 'Lookbook Summer', 'Summer lookbook cover'],
            ['lookbooks', 'Lookbook Street', 'Street style lookbook cover'],

            // Page featured images
            ['pages', 'About Us Cover', 'About us page hero'],
            ['pages', 'Size Guide Cover', 'Size guide illustration'],

            // General gallery assets
            ['gallery', 'Gallery 4', 'Fashion gallery image'],
            ['gallery', 'Gallery 5', 'Fashion gallery image'],
            ['gallery', 'Gallery 6', 'Fashion gallery image'],
            ['gallery', 'Women Look 1', 'Women outfit'],
            ['gallery', 'Men Look 1', 'Men outfit'],
        ];

        $count = count($pool);

        return collect($entries)->map(fn (array $entry, int $i) => [
            $pool[$i % $count], // relative public path, e.g. "demo/foo.jpg"
            $entry[0],
            $entry[1],
            $entry[2],
        ])->all();
    }

    /**
     * Relative public paths (e.g. "demo/foo.jpg") to every demo image, sorted.
     *
     * @return array<int, string>
     */
    protected function demoImages(): array
    {
        return collect(glob(public_path('demo').'/*.{jpg,jpeg,png}', GLOB_BRACE) ?: [])
            ->sort()
            ->map(fn (string $abs) => 'demo/'.basename($abs))
            ->values()
            ->all();
    }
}
