<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Lunar\Panel\Facades\Panel;
use Modules\Content\Models\Banner;
use Modules\Content\Models\Page;
use Modules\Content\Models\Redirect;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The shop's own admin screens on the Lunar panel.
 *
 * The panel covers products, orders, customers and the settings tree
 * first-party; these cover what it cannot know about — this shop's content
 * tables. They are declared as schemas (Modules\Content\Panel\*) rather than
 * hand-written pages, so what is under test is really the engine: routing,
 * validation, the row/field payloads the Vue pages consume, and the permission
 * gate.
 */
class PanelContentResourceTest extends TestCase
{
    private function admin(): Staff
    {
        return Staff::factory()->create(['admin' => true]);
    }

    private function actingAsAdmin(): static
    {
        $this->actingAs($this->admin(), 'staff');

        return $this;
    }

    public function test_index_renders_declared_columns_and_rows(): void
    {
        Banner::create(['title' => 'Thu Đông', 'position' => 'left', 'active' => true, 'sort' => 1]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('shop/resource/Index')
                ->where('resource.key', 'banners')
                ->has('rows', 1)
                ->where('rows.0.title', 'Thu Đông')
                // A select column shows its label, not the stored value: the
                // table is for reading, the form is what round-trips the value.
                ->where('rows.0.position', __('admin.banner.pos_left'))
                ->has('rows.0._actions.edit')
                ->has('rows.0._actions.destroy')
                // The column contract DataTable.vue expects.
                ->where('columns.0.key', 'title')
                ->has('meta.last_page'),
            );
    }

    public function test_search_filters_the_index(): void
    {
        Banner::create(['title' => 'Thu Đông', 'active' => true]);
        Banner::create(['title' => 'Xuân Hè', 'active' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.index', ['q' => 'Xuân']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('rows', 1)
                ->where('rows.0.title', 'Xuân Hè'),
            );
    }

    public function test_create_form_exposes_the_declared_fields_only(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('shop/resource/Form')
                ->where('isNew', true)
                // Defaults come from the schema so a new record opens the way
                // it will be stored, not blank.
                ->where('record.position', 'center')
                ->where('record.active', true)
                ->where('fields.0.name', 'title')
                ->where('fields.0.required', true),
            );
    }

    public function test_store_persists_and_redirects_to_edit(): void
    {
        $this->actingAsAdmin()
            ->post(route('panel.shop.banners.store'), [
                'title' => 'Banner mới',
                'position' => 'right',
                'sort' => 3,
                'active' => true,
            ])
            ->assertRedirect();

        $banner = Banner::firstWhere('title', 'Banner mới');

        $this->assertNotNull($banner);
        $this->assertSame('right', $banner->position);
        $this->assertSame(3, $banner->sort);
    }

    public function test_update_saves_and_destroy_deletes(): void
    {
        $banner = Banner::create(['title' => 'Cũ', 'active' => true]);

        $this->actingAsAdmin()
            ->put(route('panel.shop.banners.update', $banner), ['title' => 'Mới', 'active' => false])
            ->assertRedirect();

        $this->assertSame('Mới', $banner->fresh()->title);
        $this->assertFalse($banner->fresh()->active);

        $this->actingAsAdmin()
            ->delete(route('panel.shop.banners.destroy', $banner))
            ->assertRedirect(route('panel.shop.banners.index'));

        $this->assertNull($banner->fresh());
    }

    public function test_required_field_is_enforced(): void
    {
        $this->actingAsAdmin()
            ->post(route('panel.shop.banners.store'), ['title' => ''])
            ->assertSessionHasErrors('title');

        $this->assertSame(0, Banner::count());
    }

    /**
     * The redirect middleware matches on `old_url`, so a duplicate would make
     * which target wins arbitrary.
     */
    public function test_duplicate_redirect_source_is_rejected_but_editing_itself_is_not(): void
    {
        Redirect::create(['old_url' => '/cu', 'new_url' => '/moi', 'status_code' => 301, 'active' => true]);
        $second = Redirect::create(['old_url' => '/khac', 'new_url' => '/moi', 'status_code' => 301, 'active' => true]);

        $this->actingAsAdmin()
            ->put(route('panel.shop.redirects.update', $second), [
                'old_url' => '/cu',
                'new_url' => '/moi',
                'status_code' => 301,
            ])
            ->assertSessionHasErrors('old_url');

        // The same value on the row that already owns it must still save.
        $this->actingAsAdmin()
            ->put(route('panel.shop.redirects.update', $second), [
                'old_url' => '/khac',
                'new_url' => '/moi-hon',
                'status_code' => 302,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('/moi-hon', $second->fresh()->new_url);
    }

    /**
     * An int column behind a <select> has to reach the browser as a string or
     * no option matches, and has to come back as an int.
     */
    public function test_integer_select_round_trips(): void
    {
        $redirect = Redirect::create(['old_url' => '/a', 'new_url' => '/b', 'status_code' => 410, 'active' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.redirects.edit', $redirect))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('record.status_code', '410'));

        $this->actingAsAdmin()
            ->put(route('panel.shop.redirects.update', $redirect), [
                'old_url' => '/a',
                'new_url' => '/b',
                'status_code' => '302',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(302, $redirect->fresh()->status_code);
    }

    /** A JSON column is edited as text and must survive the round trip. */
    public function test_json_field_round_trips_and_rejects_malformed_input(): void
    {
        $page = Page::create(['title' => 'Về chúng tôi', 'slug' => 've-chung-toi']);

        $this->actingAsAdmin()
            ->put(route('panel.shop.pages.update', $page->id), [
                'title' => 'Về chúng tôi',
                'slug' => 've-chung-toi',
                'og_data' => '{"og:type":"website"}',
            ])
            ->assertRedirect();

        $this->assertSame(['og:type' => 'website'], $page->fresh()->og_data);

        $this->actingAsAdmin()
            ->get(route('panel.shop.pages.edit', $page->id))
            ->assertInertia(fn (AssertableInertia $inertia) => $inertia
                ->where('record.og_data', json_encode(
                    ['og:type' => 'website'],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                )),
            );

        $this->actingAsAdmin()
            ->put(route('panel.shop.pages.update', $page->id), [
                'title' => 'Về chúng tôi',
                'slug' => 've-chung-toi',
                'og_data' => '{khong-phai-json',
            ])
            ->assertSessionHasErrors('og_data');
    }

    /**
     * The gate the panel installs only grants an ability the access-control
     * manifest knows, and the manifest is built from the permissions table — so
     * the migration that creates `content:manage` is what makes these screens
     * reachable at all.
     */
    public function test_permission_row_exists_and_gates_non_admin_staff(): void
    {
        $this->assertTrue(
            Permission::where('name', 'content:manage')->where('guard_name', 'staff')->exists(),
        );

        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->get(route('panel.shop.banners.index'))
            ->assertForbidden();

        $staff->givePermissionTo('content:manage');

        $this->actingAs($staff->fresh(), 'staff')
            ->get(route('panel.shop.banners.index'))
            ->assertOk();
    }

    /**
     * The add-on's Vue bundle only reaches the browser if the section
     * registered its Vite module under the build directory the assets are
     * actually written to.
     */
    public function test_the_addon_vite_bundle_is_registered_and_built(): void
    {
        $vites = Panel::registeredVites();

        $this->assertArrayHasKey('shop', $vites);
        $this->assertSame('vendor/lunar-panel/shop/build', $vites['shop']['buildDirectory']);

        $manifest = public_path($vites['shop']['buildDirectory'].'/manifest.json');

        $this->assertFileExists($manifest, 'Chạy `npm run build:panel` trước.');
        $this->assertArrayHasKey(
            $vites['shop']['input'],
            json_decode(file_get_contents($manifest), true),
        );
    }

    /**
     * The registration above is only half of it: the panel's layout has to
     * actually emit the bundle's <script>, or the pages resolve to nothing and
     * the screen renders blank with no server-side error to notice.
     */
    public function test_the_panel_html_loads_the_addon_bundle(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('vendor/lunar-panel/shop/build/manifest.json')),
            true,
        );

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.index'))
            ->assertOk()
            ->assertSee('/vendor/lunar-panel/shop/build/'.$manifest['resources/js/panel/index.js']['file'], false);
    }

    /** Every declared resource must be reachable and navigable. */
    public function test_every_registered_resource_has_a_working_index(): void
    {
        foreach (['banners', 'pages', 'redirects'] as $key) {
            $this->actingAsAdmin()
                ->get(route("panel.shop.{$key}.index"))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('resource.key', $key));
        }
    }
}
