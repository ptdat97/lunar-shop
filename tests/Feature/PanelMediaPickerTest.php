<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Lunar\Core\Models\Staff;
use Modules\Assets\Services\MediaLibraryService;
use Modules\Content\Models\Banner;
use Tests\Concerns\CreatesStorefrontData;
use Tests\TestCase;

/**
 * The media library behind the panel's image fields.
 *
 * These columns hold a Lunar Asset id, not a path — which is why a plain text
 * box was the wrong control for them: an admin would have been typing an id
 * blind, and the preview beside it could never have resolved.
 */
class PanelMediaPickerTest extends TestCase
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

    public function test_the_browser_lists_library_images(): void
    {
        $id = $this->upload();

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.index'))
            ->assertOk()
            ->assertJsonPath('items.0.id', $id)
            ->assertJsonStructure(['items' => [['id', 'name', 'type', 'url', 'thumb']], 'folders', 'page', 'lastPage']);
    }

    public function test_the_browser_filters_by_search_and_folder(): void
    {
        $this->upload('mua-thu.jpg', 'banners');
        $this->upload('mua-dong.jpg', 'pages');

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.index', ['search' => 'mua-thu']))
            ->assertOk()
            ->assertJsonCount(1, 'items');

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.index', ['folder' => 'pages']))
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'mua-dong');
    }

    /**
     * The picker asks for one asset's preview itself. Plumbing previews through
     * every payload would mean every controller knowing about media — and an
     * image field can sit at any depth, inside a nested repeater.
     */
    public function test_a_single_preview_is_fetchable_by_id(): void
    {
        $id = $this->upload();

        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.show', $id))
            ->assertOk()
            ->assertJsonPath('id', $id);

        // A deleted asset leaves its id behind on the row; the picker has to
        // cope rather than render a broken image.
        $this->actingAsAdmin()
            ->getJson(route('panel.shop.media.show', 999999))
            ->assertNotFound();
    }

    public function test_uploading_from_the_picker_returns_the_new_asset(): void
    {
        $response = $this->actingAsAdmin()
            ->postJson(route('panel.shop.media.store'), [
                'file' => UploadedFile::fake()->image('hero.jpg', 1200, 800),
                'folder' => 'banners',
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'name', 'url', 'thumb']);

        // The id it hands back is the one the form stores.
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

    /** The library is content, so it rides on the content permission. */
    public function test_the_library_requires_the_content_permission(): void
    {
        $staff = Staff::factory()->create(['admin' => false]);

        $this->actingAs($staff, 'staff')
            ->getJson(route('panel.shop.media.index'))
            ->assertForbidden();
    }

    /**
     * An image field must reach the form as a picker, carrying its own chrome
     * strings — not as a text box.
     */
    public function test_image_fields_ship_the_pickers_labels(): void
    {
        $banner = Banner::create(['title' => 'Thu Đông', 'active' => true]);

        $this->actingAsAdmin()
            ->get(route('panel.shop.banners.edit', $banner->id))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) {
                $image = collect($page->toArray()['props']['fields'])->firstWhere('name', 'image');

                $this->assertSame('image', $image['type']);
                $this->assertSame(__('admin.media.browse'), $image['mediaLabels']['pick']);
                $this->assertArrayHasKey('allFolders', $image['mediaLabels']);
            });
    }

    /** What the picker stores is what the storefront resolves. */
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
