<?php

namespace Tests\Feature;

use Filament\Support\Assets\Asset;
use Filament\Support\Facades\FilamentAsset;
use Tests\TestCase;

/**
 * Every JS/CSS asset the panel asks the browser to load must actually exist in
 * public/.
 *
 * Filament copies its compiled assets into public/ at install time; they go
 * stale on every upgrade unless something republishes them. That is what
 * `@php artisan filament:upgrade` in composer.json's post-autoload-dump is for —
 * this project did not have that hook, so after the v3 → v4 upgrade public/js
 * still held the v3 files. v4 renamed and split the packages (actions/ and
 * schemas/ are new), so actions.js, tables.js and schemas.js all 404'd and every
 * Alpine component the admin depends on — filamentTable, filamentSchema,
 * filamentDropdown, filamentActionModals — was undefined.
 *
 * Nothing on the PHP side noticed: the whole suite stayed green while the admin
 * was unusable in a browser. Hence this test.
 */
class FilamentAssetsPublishedTest extends TestCase
{
    /**
     * @return array<string, Asset>
     */
    private function registeredAssets(): array
    {
        $assets = [
            ...FilamentAsset::getScripts(withCore: true),
            ...FilamentAsset::getStyles(),
        ];

        return array_filter(
            $assets,
            // Remote assets (CDN URLs) have nothing to publish locally.
            fn (Asset $asset) => filled($asset->getPath()),
        );
    }

    public function test_every_registered_asset_exists_in_public(): void
    {
        $assets = $this->registeredAssets();

        $this->assertNotEmpty($assets, 'The panel registered no assets at all — something is wrong with discovery.');

        $missing = [];

        foreach ($assets as $asset) {
            // getPublicPath() is already absolute — it wraps public_path() itself.
            if (! is_file($asset->getPublicPath())) {
                $missing[$asset->getId()] = $asset->getRelativePublicPath();
            }
        }

        $this->assertSame([], $missing, implode("\n", [
            'These assets are referenced by the panel but missing from public/:',
            ...array_map(
                fn (string $path, string $id) => "  - {$id} → {$path}",
                $missing,
                array_keys($missing),
            ),
            '',
            'Run: php artisan filament:assets',
        ]));
    }

    /**
     * The v4 packages whose absence broke the admin. Named explicitly so the
     * failure message points straight at the cause rather than at a count.
     */
    public function test_the_v4_javascript_packages_are_present(): void
    {
        foreach ([
            'js/filament/support/support.js',
            'js/filament/actions/actions.js',
            'js/filament/schemas/schemas.js',
            'js/filament/tables/tables.js',
            'js/filament/filament/app.js',
        ] as $path) {
            $this->assertFileExists(public_path($path));
        }
    }

    /**
     * The hook that keeps the above true after every composer install/update.
     */
    public function test_composer_republishes_assets_after_autoload_dump(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertContains(
            '@php artisan filament:upgrade',
            $composer['scripts']['post-autoload-dump'] ?? [],
            'Without this hook the published assets go stale on the next upgrade and the admin breaks in the browser only.',
        );
    }
}
