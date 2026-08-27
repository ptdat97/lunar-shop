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
            // Alpine components are a SEPARATE collection — these are the files
            // fetched lazily via x-load-src, and they include select.js, the one
            // that actually broke. Leaving them out is how the first version of
            // this test passed while the admin was still throwing.
            ...FilamentAsset::getAlpineComponents(),
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
     * Existing is not enough — the copy has to be the CURRENT one.
     *
     * This is the sharper version of the bug above, and the one that actually
     * cost a second round trip. `select.js` shipped in v3 as well, so after the
     * upgrade the file was present but held v3 code, while the URL Filament
     * generated already carried `?v=4.12.6.0`. The browser cached v3 bytes under
     * a v4 URL, and Choices.js — which v4 no longer even uses — threw
     * "Expected one of the following types text|select-one|select-multiple" on
     * markup v4 renders as a plain <div>.
     *
     * A missing file is loud. A stale one is silent.
     */
    public function test_every_published_asset_matches_the_package_it_came_from(): void
    {
        $stale = [];

        foreach ($this->registeredAssets() as $asset) {
            $source = $asset->getPath();
            $published = $asset->getPublicPath();

            if (! is_file($source) || ! is_file($published)) {
                continue; // absence is the other test's job
            }

            if (md5_file($source) !== md5_file($published)) {
                $stale[$asset->getId()] = $asset->getRelativePublicPath();
            }
        }

        $this->assertSame([], $stale, implode("\n", [
            'These published assets no longer match the version in vendor/:',
            ...array_map(
                fn (string $path, string $id) => "  - {$id} → {$path}",
                $stale,
                array_keys($stale),
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
