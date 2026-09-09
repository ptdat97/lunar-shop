<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Lunar\Core\Models\Collection;
use Modules\Content\Models\PageSection;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The home page, edited as a table of sections.
 *
 * This is the resource engine's hard case and the reason the conditional
 * machinery exists at all: eight section types, each with its own settings
 * shape, all living in one `settings` JSON column — and two of them
 * (hero-slider, lookbook) storing their content under the very same key.
 */
class PanelPageSectionTest extends TestCase
{
    // A collection needs a default Language before it can generate its URL, and
    // that only exists once the Lunar base data is seeded.
    use CreatesStorefrontData;


    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    private function section(string $type, array $settings = []): PageSection
    {
        return PageSection::create([
            'page_handle' => 'home',
            'type' => $type,
            'sort' => 1,
            'enabled' => true,
            'settings' => $settings,
        ]);
    }

    /** A dot-named field reads a path inside the JSON column, not a column. */
    public function test_settings_paths_reach_the_form_nested(): void
    {
        $section = $this->section('hero-slider', [
            'slides' => [
                ['title' => 'Thu Đông', 'image' => '/a.jpg', 'button_url' => '/search'],
            ],
        ]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.page-sections.edit', $section->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('shop/resource/Form')
                ->where('record.type', 'hero-slider')
                ->where('record.settings.slides.0.title', 'Thu Đông')
                ->where('record.settings.slides.0.image', '/a.jpg'),
            );
    }

    /** A repeater round-trips as a list of objects through the JSON column. */
    public function test_repeater_rows_are_saved_in_order(): void
    {
        $section = $this->section('iconbox');

        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'iconbox',
                'sort' => 1,
                'enabled' => true,
                'settings' => [
                    'items' => [
                        ['icon' => 'icon-truck', 'heading' => 'Giao nhanh', 'text' => '2 ngày'],
                        ['icon' => 'icon-sealCheck', 'heading' => 'Chính hãng', 'text' => '100%'],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $items = $section->fresh()->settings['items'];

        $this->assertCount(2, $items);
        $this->assertSame('Giao nhanh', $items[0]['heading']);
        $this->assertSame('Chính hãng', $items[1]['heading']);
    }

    /**
     * The whole point of the conditional branches: hero-slider and lookbook
     * both write `settings.slides` with different sub-fields, so exactly one
     * branch may be live for a given type. If both were validated at once the
     * rules would collide and one branch's would silently win.
     */
    public function test_only_the_chosen_types_branch_is_validated(): void
    {
        $section = $this->section('lookbook');

        // `position` and `pin_title` exist only on the lookbook branch.
        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'lookbook',
                'sort' => 1,
                'enabled' => true,
                'settings' => [
                    'slides' => [
                        ['banner' => '/b.jpg', 'pin_title' => 'Áo khoác', 'position' => 'position3'],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Áo khoác', $section->fresh()->settings['slides'][0]['pin_title']);

        // A product-tabs row requires a label; that rule must not apply while
        // the section is a lookbook.
        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'product-tabs',
                'sort' => 1,
                'enabled' => true,
                'settings' => ['tabs' => [['label' => '', 'product_ids' => []]]],
            ])
            ->assertSessionHasErrors('settings.tabs.0.label');
    }

    /** Numeric bounds differ per branch (flash sale caps at 24, slider at 50). */
    public function test_branch_specific_numeric_bounds_apply(): void
    {
        $section = $this->section('flash-sale');

        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'flash-sale',
                'sort' => 1,
                'enabled' => true,
                'settings' => ['heading' => 'Giờ vàng', 'limit' => 40],
            ])
            ->assertSessionHasErrors('settings.limit');

        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'promotion-slider',
                'sort' => 1,
                'enabled' => true,
                'settings' => ['heading' => 'Ưu đãi', 'limit' => 40],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(40, (int) $section->fresh()->settings['limit']);
    }

    /**
     * Saving a section busts the rendered page's config cache — the model's own
     * booted() hook, but it only fires if the panel saves through Eloquent
     * rather than a query builder.
     */
    public function test_saving_from_the_panel_busts_the_page_cache(): void
    {
        $section = $this->section('flash-sale', ['heading' => 'Cũ']);

        cache()->forever(PageSection::cacheKey('home'), ['stale']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.page-sections.update', $section->id), [
                'page_handle' => 'home',
                'type' => 'flash-sale',
                'sort' => 1,
                'enabled' => true,
                'settings' => ['heading' => 'Mới', 'limit' => 8],
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(cache()->get(PageSection::cacheKey('home')));
    }

    /** The index shows the type's label, not its handle. */
    public function test_index_lists_sections_by_sort(): void
    {
        $this->section('flash-sale')->update(['sort' => 2]);
        $this->section('hero-slider')->update(['sort' => 1]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.page-sections.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 2)
                ->where('rows.0.type', PageSection::TYPES['hero-slider'])
                ->where('rows.1.type', PageSection::TYPES['flash-sale']),
            );
    }

    /**
     * Relation options are resolved per request, so a collection added after
     * boot is pickable without clearing anything.
     */
    public function test_relation_options_come_from_the_database(): void
    {
        $collection = Collection::factory()->create();

        $this->actingAsAdmin()
            ->get(route('panel.shop.page-sections.create'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($collection) {
                // Two branches declare `settings.items` (icon boxes and the
                // collection grid), so find the repeater by what it holds
                // rather than by name — exactly the ambiguity `visibleWhen`
                // resolves at render time.
                $picker = collect($page->toArray()['props']['fields'])
                    ->flatMap(fn (array $field) => $field['children'] ?? [])
                    ->firstWhere('name', 'collection_id');

                $this->assertNotNull($picker);
                $this->assertArrayHasKey((string) $collection->id, $picker['options']);
            });
    }
}
