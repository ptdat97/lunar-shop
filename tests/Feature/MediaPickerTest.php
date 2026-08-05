<?php

namespace Tests\Feature;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Http\UploadedFile;
use Livewire\Component;
use Lunar\Models\Asset;
use Modules\Assets\Filament\Forms\MediaBrowser;
use Modules\Assets\Filament\Forms\MediaPicker;
use Modules\Assets\Filament\Forms\MediaPickerField;
use Modules\Assets\Services\MediaLibraryService;
use Tests\TestCase;

/**
 * Minimal Livewire host so the picker fields run inside a real form container —
 * a Filament field reads and writes its state through the container, so it
 * cannot be exercised standalone.
 */
class MediaPickerTestHost extends Component implements HasForms
{
    use InteractsWithForms;

    /** @var array<string, mixed> */
    public array $data = [];

    public function render(): string
    {
        return '<div></div>';
    }
}

/**
 * The MediaPicker renders picked library files as thumbnails and opens the
 * library in a modal browser (grid + search/folder filter + pagination +
 * upload), instead of the id-only <select> it used to be.
 *
 * What matters for correctness is unchanged: the field's STATE is still just a
 * Lunar Asset id (or a list of them), so every consumer that resolves those ids
 * — storefront, API resources, SkuBuilderService — keeps working. These tests
 * pin that contract plus the browsing/selection behaviour the UI depends on.
 */
class MediaPickerTest extends TestCase
{
    /** A library Asset with a real image file attached. */
    private function libraryAsset(string $name = 'shot.png', ?string $folder = null): Asset
    {
        return app(MediaLibraryService::class)->store(
            UploadedFile::fake()->image($name, 400, 400),
            $folder,
        );
    }

    /**
     * Mount a component into a real form container (bound to a Livewire host),
     * which is what gives a Filament field somewhere to read/write state.
     *
     * @template T of \Filament\Forms\Components\Component
     *
     * @param  T  $component
     * @return T
     */
    private function mount($component)
    {
        // getComponents() is what actually binds each child to the container
        // (components() only stores the schema), so call it to mount the field.
        ComponentContainer::make(new MediaPickerTestHost)
            ->statePath('data')
            ->components([$component])
            ->getComponents();

        return $component;
    }

    private function field(string $name = 'image', ?string $type = 'image', bool $multiple = false): MediaPickerField
    {
        $field = MediaPicker::make($name, type: $type, multiple: $multiple);

        $this->assertInstanceOf(MediaPickerField::class, $field);

        return $this->mount($field);
    }

    public function test_picker_state_is_still_a_bare_asset_id(): void
    {
        $asset = $this->libraryAsset();
        $field = $this->field();

        $field->state($asset->id);

        // The stored value stays the plain id — the contract every consumer
        // (storefront, API resources, SkuBuilder) resolves against.
        $this->assertSame($asset->id, $field->getState());
    }

    public function test_picker_exposes_a_thumbnail_preview_for_the_picked_file(): void
    {
        $asset = $this->libraryAsset('lookbook-cover.png');
        $field = $this->field();
        $field->state($asset->id);

        $previews = $field->getPreviews();

        $this->assertCount(1, $previews);
        $this->assertSame($asset->id, $previews[0]['id']);
        $this->assertSame('image', $previews[0]['type']);
        $this->assertSame('lookbook-cover', $previews[0]['name']);
        $this->assertNotNull($previews[0]['thumb']);
    }

    public function test_a_picked_file_deleted_from_the_library_drops_out_of_the_preview(): void
    {
        $asset = $this->libraryAsset();
        $field = $this->field();
        $field->state($asset->id);

        app(MediaLibraryService::class)->delete($asset);

        // Stale id must not fatal the form — it simply has nothing to show.
        $this->assertSame([], $field->getPreviews());
    }

    public function test_multiple_picker_previews_follow_stored_order(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');
        $field = $this->field('images', multiple: true);

        $field->state([$second->id, $first->id]);

        $this->assertSame(
            [$second->id, $first->id],
            array_column($field->getPreviews(), 'id'),
        );
    }

    public function test_remove_clears_a_single_picker_and_prunes_a_multiple_one(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');

        $single = $this->field();
        $single->state($first->id);
        $single->removeId($first->id);
        $this->assertNull($single->getState());

        $multiple = $this->field('images', multiple: true);
        $multiple->state([$first->id, $second->id]);
        $multiple->removeId($first->id);
        $this->assertSame([$second->id], $multiple->getState());
    }

    public function test_reordering_a_multiple_picker_swaps_neighbours(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');
        $third = $this->libraryAsset('c.png');

        $field = $this->field('images', multiple: true);
        $field->state([$first->id, $second->id, $third->id]);

        $field->moveId($third->id, -1);
        $this->assertSame([$first->id, $third->id, $second->id], $field->getState());

        $field->moveId($first->id, 1);
        $this->assertSame([$third->id, $first->id, $second->id], $field->getState());
    }

    public function test_reordering_past_either_end_is_a_no_op(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');

        $field = $this->field('images', multiple: true);
        $field->state([$first->id, $second->id]);

        $field->moveId($first->id, -1);   // already first
        $field->moveId($second->id, 1);   // already last

        $this->assertSame([$first->id, $second->id], $field->getState());
    }

    /** The browser field backing the picker's modal. */
    private function browser(?string $type = 'image', bool $multiple = false): MediaBrowser
    {
        $browser = $this->mount(
            MediaBrowser::make('browser')->libraryType($type)->multiple($multiple),
        );

        $browser->state(['selected' => [], 'search' => null, 'folder' => null, 'page' => 1]);

        return $browser;
    }

    public function test_browser_lists_library_files_newest_first(): void
    {
        $older = $this->libraryAsset('older.png');
        $newer = $this->libraryAsset('newer.png');

        $ids = $this->browser()->getFiles()->pluck('id')->all();

        $this->assertSame([$newer->id, $older->id], $ids);
    }

    public function test_browser_search_filters_by_file_name(): void
    {
        $this->libraryAsset('summer-hero.png');
        $winter = $this->libraryAsset('winter-hero.png');

        $browser = $this->browser();
        $browser->filter('winter', null);

        $this->assertSame([$winter->id], $browser->getFiles()->pluck('id')->all());
    }

    public function test_browser_folder_filter_narrows_to_that_folder(): void
    {
        $this->libraryAsset('loose.png');
        $filed = $this->libraryAsset('banner.png', 'banners');

        $browser = $this->browser();
        $browser->filter(null, 'banners');

        $this->assertSame([$filed->id], $browser->getFiles()->pluck('id')->all());
        $this->assertArrayHasKey('banners', $browser->getFolders());
    }

    public function test_browser_type_restriction_hides_other_media_types(): void
    {
        $image = $this->libraryAsset('photo.png');

        app(MediaLibraryService::class)->store(
            UploadedFile::fake()->create('spec.pdf', 12, 'application/pdf'),
        );

        // An image-only picker must never offer the PDF.
        $this->assertSame([$image->id], $this->browser('image')->getFiles()->pluck('id')->all());
    }

    public function test_changing_a_filter_resets_to_the_first_page(): void
    {
        $this->libraryAsset('a.png');

        $browser = $this->browser();
        $browser->goToPage(3);
        $browser->filter('a', null);

        // Otherwise the admin lands on an empty page of a narrowed result set.
        $this->assertSame(1, $browser->getFiles()->currentPage());
    }

    public function test_single_browser_selection_replaces_rather_than_accumulates(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');

        $browser = $this->browser(multiple: false);
        $browser->toggle($first->id);
        $browser->toggle($second->id);

        $this->assertSame([$second->id], $browser->getSelected());

        // Re-picking the selected file clears it.
        $browser->toggle($second->id);
        $this->assertSame([], $browser->getSelected());
    }

    public function test_multiple_browser_accumulates_and_unpicks(): void
    {
        $first = $this->libraryAsset('a.png');
        $second = $this->libraryAsset('b.png');

        $browser = $this->browser(multiple: true);
        $browser->toggle($first->id);
        $browser->toggle($second->id);
        $this->assertSame([$first->id, $second->id], $browser->getSelected());

        $browser->toggle($first->id);
        $this->assertSame([$second->id], $browser->getSelected());
    }

    public function test_uploading_from_the_modal_stores_the_file_and_preselects_it(): void
    {
        $browser = $this->browser(multiple: false);

        $count = $browser->upload([UploadedFile::fake()->image('fresh.png', 300, 300)], 'banners');

        $this->assertSame(1, $count);

        $selected = $browser->getSelected();
        $this->assertCount(1, $selected);

        // It really landed in the library, in the requested folder, and is
        // ready to confirm without a second trip through the grid.
        $preview = app(MediaLibraryService::class)->preview($selected[0]);
        $this->assertSame('fresh', $preview['name']);
        $this->assertArrayHasKey('banners', $browser->getFolders());
    }
}
