<?php

namespace Modules\FileManager\Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Asset;
use Modules\FileManager\Services\FileManager;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Seeds the Media Library with demo files (Lunar Assets) sourced from the
 * modave theme images. Other demo seeders (CMS banners/lookbooks/pages) look
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
     *
     * @return array<int, array{0:string,1:string,2:string,3:string}>
     */
    protected function files(): array
    {
        $base = 'themes/modave/images';

        return [
            // Banners / hero
            ["{$base}/banner/discover-women.jpg", 'banners', 'Discover Women', 'Women new season banner'],
            ["{$base}/banner/discover-men.jpg", 'banners', 'Discover Men', 'Men new season banner'],
            ["{$base}/slider/item-slider-activewear1.jpg", 'banners', 'Activewear Slider 1', 'Activewear hero slide'],
            ["{$base}/slider/item-slider-activewear2.jpg", 'banners', 'Activewear Slider 2', 'Activewear hero slide'],

            // Lookbook covers
            ["{$base}/gallery/gallery-1.jpg", 'lookbooks', 'Lookbook Spring', 'Spring lookbook cover'],
            ["{$base}/gallery/gallery-2.jpg", 'lookbooks', 'Lookbook Summer', 'Summer lookbook cover'],
            ["{$base}/gallery/gallery-3.jpg", 'lookbooks', 'Lookbook Street', 'Street style lookbook cover'],

            // Page featured images
            ["{$base}/banner/about-us.jpg", 'pages', 'About Us Cover', 'About us page hero'],
            ["{$base}/gallery/gallery-10.jpg", 'pages', 'Size Guide Cover', 'Size guide illustration'],

            // General gallery assets
            ["{$base}/gallery/gallery-4.jpg", 'gallery', 'Gallery 4', 'Fashion gallery image'],
            ["{$base}/gallery/gallery-5.jpg", 'gallery', 'Gallery 5', 'Fashion gallery image'],
            ["{$base}/gallery/gallery-6.jpg", 'gallery', 'Gallery 6', 'Fashion gallery image'],
            ["{$base}/products/womens/women-1.jpg", 'gallery', 'Women Look 1', 'Women outfit'],
            ["{$base}/products/mens/men-1.jpg", 'gallery', 'Men Look 1', 'Men outfit'],
        ];
    }
}
