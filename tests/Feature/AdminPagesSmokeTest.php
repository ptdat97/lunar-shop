<?php

namespace Tests\Feature;

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Livewire\Livewire;
use Lunar\Core\Models\Staff;
use Modules\Catalog\Filament\Pages\ManageProductSizing;
use Modules\Catalog\Filament\Pages\ManageProductVariants;
use Modules\Theme\Filament\Resources\CollectionResource;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * Mounts every page the admin panel registers and asserts it renders.
 *
 * The unit tests cover behaviour; this covers *wiring*. A Filament major upgrade
 * breaks pages one at a time — a renamed component here, a changed method
 * signature there — and none of it shows up until someone opens that screen. The
 * v3 → v4 upgrade is exactly how this file came to exist.
 *
 * Only index/list pages are mounted: edit and view pages need a record and are
 * covered case by case (see ProductAdminPagesTest).
 */
class AdminPagesSmokeTest extends TestCase
{
    use CreatesStorefrontData;

    /**
     * Pages that cannot be mounted bare, with the reason. Keep this list short
     * and justified — every entry is a screen nothing checks.
     */
    private const NEEDS_PARAMS = [
        // Sub-navigation pages of a product; ProductAdminPagesTest mounts these
        // with a real record.
        ManageProductVariants::class,
        ManageProductSizing::class,
    ];

    /**
     * Resources whose index page is deliberately unreachable. Listed explicitly
     * rather than swallowing every 404, because a real routing break looks the
     * same from here.
     */
    private const NO_INDEX_PAGE = [
        // Lunar's ListCollections::mount() calls abort(404) on purpose —
        // collections are browsed through their collection group, never a flat
        // index. Our subclass only re-groups the navigation entry.
        CollectionResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the reference data a real install always has (currency, language,
        // tax class, channel). Without it these pages fail on missing defaults,
        // which is a fixture problem, not a wiring one.
        $this->seedBaseData();

        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');
        Filament::setCurrentPanel($this->panel());
    }

    private function panel(): Panel
    {
        return Filament::getPanel('lunar');
    }

    /**
     * @return array<int, class-string>
     */
    private function mountablePages(): array
    {
        return array_values(array_filter(
            $this->panel()->getPages(),
            fn (string $page) => ! in_array($page, self::NEEDS_PARAMS, true),
        ));
    }

    public function test_every_registered_page_renders(): void
    {
        $pages = $this->mountablePages();

        $this->assertGreaterThan(10, count($pages), 'Panel registered suspiciously few pages.');

        $failures = [];

        foreach ($pages as $page) {
            try {
                Livewire::test($page)->assertOk();
            } catch (\Throwable $e) {
                $failures[$page] = $e::class.': '.$e->getMessage();
            }
        }

        $this->assertSame([], $failures, "Admin pages failed to render:\n".$this->format($failures));
    }

    public function test_every_resource_list_page_renders(): void
    {
        $failures = [];
        $mounted = 0;

        foreach ($this->panel()->getResources() as $resource) {
            if (in_array($resource, self::NO_INDEX_PAGE, true)) {
                continue;
            }

            $index = $resource::getPages()['index'] ?? null;

            if (! $index instanceof PageRegistration) {
                continue;
            }

            $page = $index->getPage();
            $mounted++;

            try {
                Livewire::test($page)->assertOk();
            } catch (\Throwable $e) {
                $failures[$resource] = $e::class.': '.$e->getMessage();
            }
        }

        $this->assertGreaterThan(20, $mounted, 'Suspiciously few resource list pages were mounted.');
        $this->assertSame([], $failures, "Resource list pages failed to render:\n".$this->format($failures));
    }

    /**
     * @param  array<string, string>  $failures
     */
    private function format(array $failures): string
    {
        return collect($failures)
            ->map(fn (string $error, string $subject) => "  - {$subject}\n      {$error}")
            ->implode("\n");
    }
}
