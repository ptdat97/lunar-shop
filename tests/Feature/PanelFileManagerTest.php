<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Staff;
use Lunar\Panel\PanelManager;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Content\Models\Banner;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The file manager (modules/Assets): the page, the iframe picker every image
 * field opens, and the JSON API both run on.
 *
 * The rule these tests hold in place is that there is ONE way into the
 * library. An image field does not list or upload anything itself — it opens
 * the picker and stores the id it hands back — so everything about uploading,
 * folders and metadata is asserted here, once.
 */
class PanelFileManagerTest extends TestCase
{
    use CreatesStorefrontData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    private function actingAsAdmin(): static
    {
        $this->actingAs(Staff::factory()->create(['admin' => true]), 'staff');

        return $this;
    }

    private function upload(string $name = 'banner.jpg', ?string $folder = null): int
    {
        return app(MediaLibraryService::class)
            ->store(UploadedFile::fake()->image($name, 800, 600), $folder)
            ->id;
    }

    private function media(int $assetId)
    {
        return Asset::with('file')->find($assetId)?->file;
    }

    /* ------------------------------------------------------------ pages */

    public function test_the_manager_is_a_page_of_its_own(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.media.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('shop/media/Index')
                ->where('labels.title', __('admin.file_manager.title'))
                ->where('base', route('panel.shop.media.index'))
                ->where('unfiled', MediaLibraryService::UNFILED)
                ->where('maxUploadKb', (int) config('lunar.media.max_upload_kb', 8192)));
    }

    /** Beside the banners and pages whose images come from it. */
    public function test_the_manager_sits_in_the_content_group_of_the_sidebar(): void
    {
        $staff = Staff::factory()->create(['admin' => true]);
        $groups = app(PanelManager::class)->navigation()->toArray($staff)['groups'] ?? [];
        $content = collect($groups)->firstWhere('key', 'shop-content');

        $this->assertNotNull($content);
        $this->assertSame(__('panel.nav.content'), $content['label'], 'Nhóm Nội dung phải do ShopSection đặt tên, không phải AssetsSection.');
        $this->assertContains('media', array_column($content['items'], 'key'));
    }

    public function test_the_picker_renders_the_manager_with_the_openers_options(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.media.picker', ['type' => 'image', 'multiple' => 1, 'folder' => 'banners', 'channel' => 'abc123']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('shop/media/Picker')
                ->where('pick.type', 'image')
                ->where('pick.multiple', true)
                ->where('pick.folder', 'banners')
                ->where('pick.channel', 'abc123')
                ->where('labels.picker_title', __('admin.file_manager.picker_title')));
    }

    public function test_an_unknown_picker_type_falls_back_to_images(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.media.picker', ['type' => 'executable']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pick.type', 'image')
                ->where('pick.multiple', false));
    }

    /**
     * Image fields sit on pages this module does not render; they find the
     * picker through a shared prop, so the route is written down once.
     */
    public function test_every_panel_page_is_told_where_the_file_manager_is(): void
    {
        $banner = Banner::create(['title' => 'Thu Đông', 'active' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.edit', $banner->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fileManager.url', route('panel.shop.media.picker'))
                ->where('fileManager.base', route('panel.shop.media.index')));
    }

    /**
     * Without the library's permission the prop is null. Image fields then
     * disable their button, and Lunar's own gallery/swatch uploaders keep the
     * file dialog they ship with — nativeUploadBridge.js stands down rather
     * than opening a picker that would answer 403.
     */
    public function test_staff_without_the_library_permission_get_no_file_manager(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);
        $staff->givePermissionTo('catalog:manage-products');

        $this->actingAs($staff, 'staff')
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('fileManager', null));
    }

    /** The bridge's notice runs outside the manager, so its strings ride along. */
    public function test_the_shared_prop_carries_the_bridge_strings(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('fileManager.labels.picker_title', __('admin.file_manager.picker_title'))
                ->where('fileManager.labels.bridge_fetch_failed', __('admin.file_manager.bridge_fetch_failed')));
    }

    /**
     * Opened from a product page, new uploads are filed under `products`
     * rather than left unfiled — slugged like every other folder write.
     */
    public function test_the_picker_takes_an_upload_folder_from_its_opener(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.media.picker', ['upload_folder' => 'Product Types']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pick.uploadFolder', 'product-types'));

        // A malformed query is ignored, not a 500.
        $this->actingAsAdmin()
            ->get(route('panel.shop.media.picker').'?upload_folder[]=x&folder[]=y')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pick.uploadFolder', '')
                ->where('pick.folder', ''));
    }

    /**
     * The picker only works inside an iframe on the panel. X-Frame-Options
     * SAMEORIGIN allows that; an ENFORCED `frame-ancestors 'none'` would blank
     * every image field's popup — which is why the panel must stay report-only
     * even when the storefront enforces.
     */
    public function test_the_picker_can_be_framed_by_the_panel(): void
    {
        config(['security.csp.mode' => 'enforce']);

        $response = $this->actingAsAdmin()->get(route('panel.shop.media.picker'));

        $response->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertFalse(
            $response->headers->has('Content-Security-Policy'),
            'Một CSP enforce trên panel sẽ chặn iframe của file manager (frame-ancestors \'none\').',
        );
    }

    public function test_the_library_requires_the_content_permission(): void
    {
        $this->actingAs(Staff::factory()->create(['admin' => false]), 'staff');

        $this->get(route('panel.shop.media.index'))->assertForbidden();
        $this->get(route('panel.shop.media.picker'))->assertForbidden();
        $this->getJson(route('panel.shop.media.files'))->assertForbidden();
        $this->postJson(route('panel.shop.media.store'), ['file' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
    }

    /* ------------------------------------------------------------ listing */

    public function test_the_list_returns_files_folders_and_paging(): void
    {
        $id = $this->upload('mua-thu.jpg', 'banners');

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.files'))
            ->assertOk()
            ->assertJsonPath('items.0.id', $id)
            ->assertJsonPath('items.0.folder', 'banners')
            ->assertJsonPath('folders', [['name' => 'banners', 'count' => 1]])
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'items' => [['id', 'name', 'file_name', 'type', 'mime', 'size', 'url', 'thumb', 'large', 'alt', 'title', 'folder', 'created_at']],
                'folders', 'total', 'page', 'lastPage',
            ]);
    }

    /** An empty library still sends a list — never `{}` for the client to special-case. */
    public function test_folders_are_a_list_even_when_empty(): void
    {
        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.files'))
            ->assertOk()
            ->assertExactJson(['items' => [], 'folders' => [], 'total' => 0, 'page' => 1, 'lastPage' => 1]);
    }

    public function test_the_list_filters_by_search_folder_and_unfiled(): void
    {
        $this->upload('mua-thu.jpg', 'banners');
        $pages = $this->upload('mua-dong.jpg', 'pages');
        $loose = $this->upload('logo.png');

        $admin = $this->actingAsAdmin();

        $admin->getJson(route('panel.shop.media.files', ['search' => 'mua-thu']))
            ->assertJsonCount(1, 'items');

        $admin->getJson(route('panel.shop.media.files', ['folder' => 'pages']))
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $pages);

        $admin->getJson(route('panel.shop.media.files', ['folder' => MediaLibraryService::UNFILED]))
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $loose);
    }

    public function test_the_list_sorts(): void
    {
        $b = $this->upload('b-anh.jpg');
        $a = $this->upload('a-anh.jpg');
        $c = $this->upload('c-anh.jpg');

        $admin = $this->actingAsAdmin();

        $this->assertSame([$c, $a, $b], array_column($admin->getJson(route('panel.shop.media.files'))->json('items'), 'id'));
        $this->assertSame([$b, $a, $c], array_column($admin->getJson(route('panel.shop.media.files', ['sort' => 'oldest']))->json('items'), 'id'));
        $this->assertSame([$a, $b, $c], array_column($admin->getJson(route('panel.shop.media.files', ['sort' => 'name']))->json('items'), 'id'));
    }

    public function test_the_list_rejects_an_unknown_sort(): void
    {
        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.files', ['sort' => 'id; drop table']))
            ->assertJsonValidationErrors('sort');
    }

    /**
     * A field asks for its preview by id. A deleted asset leaves the id behind
     * on the row, and the field has to cope rather than render a broken image.
     */
    public function test_a_single_preview_is_fetchable_by_id(): void
    {
        $id = $this->upload();

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.show', $id))
            ->assertOk()
            ->assertJsonPath('id', $id);

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.show', 999999))
            ->assertNotFound();
    }

    /* ------------------------------------------------------------ uploads */

    public function test_uploading_returns_the_new_asset_in_its_folder(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.store'), [
                'file' => UploadedFile::fake()->image('Ảnh Hero.jpg', 1200, 800),
                'folder' => 'Mùa Thu',
            ])
            ->assertCreated()
            ->assertJsonPath('folder', 'mua-thu')
            ->assertJsonStructure(['id', 'name', 'url', 'thumb', 'large']);

        // The id it hands back is the one a field stores.
        $this->assertNotNull(app(MediaLibraryService::class)->preview($response->json('id')));
    }

    public function test_uploading_rejects_a_non_image(): void
    {
        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.store'), [
                'file' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
            ])
            ->assertJsonValidationErrors('file');
    }

    /** Served from the shop's own origin, an SVG is a script. */
    public function test_uploading_rejects_svg(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.store'), ['file' => $svg])
            ->assertJsonValidationErrors('file');
    }

    /** Lunar's own ceiling, the same one its product gallery enforces. */
    public function test_uploading_respects_lunars_upload_ceiling(): void
    {
        config(['lunar.media.max_upload_kb' => 50]);

        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.store'), [
                'file' => UploadedFile::fake()->image('big.jpg')->size(80),
            ])
            ->assertJsonValidationErrors('file');
    }

    /* ------------------------------------------------------------ editing */

    public function test_metadata_is_editable_and_a_blank_alt_is_removed(): void
    {
        $id = $this->upload('hero.jpg');

        $this->actingAsAdmin()
            ->patchJson(route('panel.shop.media.update', $id), [
                'name' => 'Hero mùa thu',
                'alt' => 'Người mẫu mặc áo khoác dạ',
                'title' => 'Thu Đông 2026',
                'folder' => 'Banner Trang Chủ',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Hero mùa thu')
            ->assertJsonPath('alt', 'Người mẫu mặc áo khoác dạ')
            ->assertJsonPath('folder', 'banner-trang-chu');

        $this->actingAsAdmin()
            ->patchJson(route('panel.shop.media.update', $id), ['alt' => ''])
            ->assertOk()
            ->assertJsonPath('alt', null)
            // Keys not sent are left alone.
            ->assertJsonPath('title', 'Thu Đông 2026');

        $this->assertFalse($this->media($id)->hasCustomProperty('alt'));
    }

    /** The whole point of replacing: every row pointing at the id follows. */
    public function test_replacing_keeps_the_asset_id(): void
    {
        $id = $this->upload('cu.jpg', 'banners');
        app(MediaLibraryService::class)->update(Asset::find($id), ['alt' => 'Giữ nguyên']);

        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.replace', $id), [
                'file' => UploadedFile::fake()->image('moi.png', 400, 400),
            ])
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('file_name', 'moi.png')
            ->assertJsonPath('folder', 'banners')
            ->assertJsonPath('alt', 'Giữ nguyên');

        $this->assertSame(1, Asset::find($id)->getMedia(app(MediaLibraryService::class)->collection())->count());
    }

    public function test_files_move_between_folders_and_out_of_them(): void
    {
        $a = $this->upload('a.jpg');
        $b = $this->upload('b.jpg', 'old');

        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.move'), ['ids' => [$a, $b], 'folder' => 'Lookbook'])
            ->assertOk()
            ->assertJsonPath('moved', 2);

        $this->assertSame('lookbook', $this->media($a)->getCustomProperty('folder'));
        $this->assertSame('lookbook', $this->media($b)->getCustomProperty('folder'));

        $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.move'), ['ids' => [$a], 'folder' => null])
            ->assertJsonPath('moved', 1);

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.files', ['folder' => MediaLibraryService::UNFILED]))
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $a);
    }

    public function test_bulk_delete_removes_the_files_and_skips_unknown_ids(): void
    {
        $a = $this->upload('a.jpg');
        $b = $this->upload('b.jpg');
        $keep = $this->upload('c.jpg');

        $this->actingAsAdmin()
            ->deleteJson(route('panel.shop.media.destroy'), ['ids' => [$a, $b, 999999]])
            ->assertOk()
            ->assertJsonPath('deleted', 2);

        $this->assertNull(Asset::find($a));
        $this->assertNull(Asset::find($b));
        $this->assertNotNull(Asset::find($keep));
    }

    public function test_renaming_a_folder_moves_its_files_and_merges_onto_an_existing_one(): void
    {
        $a = $this->upload('a.jpg', 'banner-cu');
        $b = $this->upload('b.jpg', 'banners');

        $this->actingAsAdmin()
            ->patchJson(route('panel.shop.media.folders.rename', 'banner-cu'), ['name' => 'Banners'])
            ->assertOk()
            ->assertJsonPath('folder', 'banners');

        $this->assertSame('banners', $this->media($a)->getCustomProperty('folder'));
        $this->assertSame(['banners' => 2], app(MediaLibraryService::class)->folderCounts());
        $this->assertSame('banners', $this->media($b)->getCustomProperty('folder'));
    }

    /** "!!!" passes `required` but would slug to nothing and unfile everything. */
    public function test_renaming_to_a_name_with_no_letters_is_refused(): void
    {
        $a = $this->upload('a.jpg', 'banners');

        $this->actingAsAdmin()
            ->patchJson(route('panel.shop.media.folders.rename', 'banners'), ['name' => '!!!'])
            ->assertJsonValidationErrors('name');

        $this->assertSame('banners', $this->media($a)->getCustomProperty('folder'));
    }

    /* --------------------------------------------------- fields that use it */

    /**
     * An image field ships only its own chrome — preview and the button that
     * opens the manager. Search, folders and upload are the manager's.
     */
    public function test_image_fields_ship_only_the_openers_labels(): void
    {
        $banner = Banner::create(['title' => 'Thu Đông', 'active' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.edit', $banner->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $image = collect($page->toArray()['props']['fields'])->firstWhere('name', 'image');

                $this->assertSame('image', $image['type']);
                $this->assertSame(
                    ['pick', 'change', 'remove', 'missing'],
                    array_keys($image['mediaLabels']),
                );
                $this->assertSame(__('admin.media.browse'), $image['mediaLabels']['pick']);
            });
    }

    /** Body copy takes its images through the manager too. */
    public function test_html_fields_offer_to_insert_from_the_manager(): void
    {
        $this->actingAsAdmin()
            ->get(route('panel.shop.pages.create'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $content = collect($page->toArray()['props']['fields'])->firstWhere('type', 'html');

                $this->assertNotNull($content);
                $this->assertSame(__('admin.media.insert'), $content['mediaLabels']['insert']);
            });
    }

    /**
     * What the picker hands back is what the field stores and what the
     * storefront resolves. The field sends it as a string: the form posts JSON
     * and the rule is `string`.
     */
    public function test_the_stored_asset_id_resolves_to_a_url(): void
    {
        $id = $this->upload();

        $this->actingAsAdmin()
            ->post(route('panel.shop.banners.store'), [
                'title' => 'Thu Đông',
                'image' => (string) $id,
                'active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $banner = Banner::firstWhere('title', 'Thu Đông');

        $this->assertSame((string) $id, $banner->image);
        $this->assertNotNull(app(MediaLibraryService::class)->url($banner->image));
    }
}
