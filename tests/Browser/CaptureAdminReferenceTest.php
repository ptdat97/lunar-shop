<?php

namespace Tests\Browser;

use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Laravel\Dusk\Browser;
use Lunar\Admin\Models\Staff;
use Lunar\Models\Product;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Pages\ManageProductVariants;
use Tests\DuskTestCase;

/**
 * Photographs every Filament admin screen before it is deleted.
 *
 * The Lunar 2.0 upgrade replaces the whole admin with the Inertia panel
 * (docs/guides/upgrade-lunar-2.0.md), so 64 PHP files and 17 Blade views get
 * REWRITTEN, not migrated. Rewriting a screen with no picture of what it used to
 * do is how details quietly go missing — a filter, a badge, a helper text nobody
 * remembers was there.
 *
 * This is a one-shot capture tool, not a regression test: it asserts almost
 * nothing. Run it once while Filament still exists, keep the images, then delete
 * this file along with the rest of the Filament admin.
 *
 *   php artisan dusk --filter=CaptureAdminReferenceTest
 *
 * Images land in tests/Browser/screenshots/ (gitignored) — copy them somewhere
 * durable before the upgrade removes the admin.
 */
class CaptureAdminReferenceTest extends DuskTestCase
{
    /** Pages needing a record in the URL; captured via the product below. */
    private const RECORD_PAGES = [
        ManageProductVariants::class,
        ManageProductSizing::class,
    ];

    private function slugFor(string $class): string
    {
        return str_replace('\\', '-', class_basename($class));
    }

    public function test_capture_every_admin_screen(): void
    {
        $panel = Filament::getPanel('lunar');

        $urls = [];

        // Standalone pages.
        foreach ($panel->getPages() as $page) {
            if (in_array($page, self::RECORD_PAGES, true)) {
                continue;
            }

            try {
                $urls['page-'.$this->slugFor($page)] = $page::getUrl(panel: 'lunar');
            } catch (\Throwable) {
                // Needs parameters we do not have; the sub-navigation pages below
                // cover the ones that matter.
            }
        }

        // One list screen per resource.
        foreach ($panel->getResources() as $resource) {
            $index = $resource::getPages()['index'] ?? null;

            if (! $index instanceof PageRegistration) {
                continue;
            }

            try {
                $urls['resource-'.$this->slugFor($resource)] = $resource::getUrl('index', panel: 'lunar');
            } catch (\Throwable) {
            }
        }

        // The two product sub-pages, plus the stock edit screen they hang off.
        $productId = Product::query()->value('id');

        if ($productId) {
            $urls['product-edit'] = "/lunar/products/{$productId}/edit";
            $urls['product-variants'] = "/lunar/products/{$productId}/variants";
            $urls['product-sizing'] = "/lunar/products/{$productId}/sizing";
        }

        $this->assertNotEmpty($urls, 'No admin screens resolved — nothing to capture.');

        $captured = 0;
        $failed = [];

        $this->browse(function (Browser $browser) use ($urls, &$captured, &$failed) {
            $browser->loginAs(Staff::findOrFail(1), 'staff');

            foreach ($urls as $name => $url) {
                try {
                    $browser->visit($url)->pause(1800)->screenshot('admin-'.$name);
                    $captured++;
                } catch (\Throwable $e) {
                    $failed[$name] = substr($e->getMessage(), 0, 80);
                }
            }
        });

        fwrite(STDERR, "\n=== chụp được {$captured}/".count($urls)." màn hình\n");

        foreach ($failed as $name => $why) {
            fwrite(STDERR, "    lỗi: {$name} — {$why}\n");
        }

        $this->assertGreaterThan(20, $captured, 'Captured suspiciously few admin screens.');
    }
}
