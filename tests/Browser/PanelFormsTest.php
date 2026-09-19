<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Lunar\Core\Models\Asset;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\Staff;
use Tests\DuskTestCase;

/**
 * The panel's forms, driven in a real browser.
 *
 * Everything else about these screens is covered far faster by feature tests —
 * routing, validation, what gets written. What no feature test can reach is the
 * half that only exists in the browser: conditional branches appearing and
 * disappearing, repeater rows being added and reordered, a slug following a
 * title, the media picker opening. That logic is this file's whole reason to
 * exist.
 *
 * Deliberately **read-only**: every interaction happens on a *create* form and
 * nothing is ever saved, so it is safe to run repeatedly against the dev DB —
 * the rule §3.4 of docs/guides/e2e-testing.md exists to enforce.
 */
class PanelFormsTest extends DuskTestCase
{
    /**
     * Console entries that are noise rather than failure. Mirrors
     * StorefrontSmokeTest::IGNORED — add here only after proving benign.
     */
    private const IGNORED = [
        'was preloaded using link preload but not used',
        'favicon',
        // The panel asks Gravatar for a staff avatar with `d=404`, which is
        // Gravatar's documented way of saying "tell me if there isn't one" —
        // the 404 IS the answer, and the panel then renders initials. Noise,
        // not a fault.
        'gravatar.com/avatar',
    ];

    private function staffId(): int
    {
        $staff = Staff::query()->where('admin', true)->first() ?? Staff::query()->first();

        if (! $staff) {
            $this->markTestSkipped('DB dev không có tài khoản nhân viên nào để đăng nhập.');
        }

        return $staff->id;
    }

    /** @return array<int, string> */
    private function consoleErrors(Browser $browser): array
    {
        $messages = [];

        foreach ($browser->driver->manage()->getLog('browser') as $entry) {
            $message = (string) ($entry['message'] ?? '');

            if ($message === '') {
                continue;
            }

            foreach (self::IGNORED as $ignore) {
                if (str_contains($message, $ignore)) {
                    continue 2;
                }
            }

            $messages[] = $message;
        }

        return $messages;
    }

    private function assertConsoleClean(Browser $browser, string $screen): void
    {
        $errors = $this->consoleErrors($browser);

        $this->assertSame([], $errors, "Console có lỗi ở [{$screen}]: ".implode(' | ', $errors));
    }

    /**
     * The add-on bundle has to load at all. A missing build shows up here as
     * "Panel page not found" in the console and an empty screen — with no
     * server-side error anywhere to notice.
     */
    public function test_a_declared_screen_renders_from_the_addon_bundle(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/banners')
                ->waitForText('Banner', 10)
                ->assertPresent('[data-field="search"]');

            $this->assertConsoleClean($browser, 'danh sách banner');
        });
    }

    /**
     * `visibleWhen`: choosing a section type swaps which branch of the form is
     * live. Two branches share the field name `settings.slides`, so the wrong
     * one appearing is not a cosmetic bug — it is the wrong schema.
     */
    public function test_choosing_a_section_type_swaps_the_visible_branch(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/page-sections/create')
                ->waitFor('[data-field="type"]', 10)

                // Nothing type-specific before a type is chosen.
                ->assertMissing('[data-field="title"]')
                ->assertMissing('[data-field="heading"]')

                // Hero slider → slides, each with a title.
                ->select('[data-field="type"]', 'hero-slider')
                ->waitForText('Slide', 5)
                ->click('[data-repeater-add]')
                ->waitFor('[data-field="title"]', 5)
                ->assertPresent('[data-field="button_url"]')

                // Icon box → a different branch entirely; the slide fields go.
                ->select('[data-field="type"]', 'iconbox')
                ->pause(300)
                ->assertMissing('[data-field="button_url"]');

            $this->assertConsoleClean($browser, 'tạo section trang chủ');
        });
    }

    /**
     * A repeater's rows are added and removed in the browser and nowhere else.
     */
    public function test_repeater_rows_can_be_added_and_removed(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/page-sections/create')
                ->waitFor('[data-field="type"]', 10)
                ->select('[data-field="type"]', 'iconbox')
                ->waitForText('Thêm', 5);

            $before = count($browser->elements('[data-field="heading"]'));

            $browser->click('[data-repeater-add]')->pause(200)->click('[data-repeater-add]')->pause(200);

            $this->assertSame($before + 2, count($browser->elements('[data-field="heading"]')));

            $browser->click('[data-repeater-remove]')->pause(200);

            $this->assertSame($before + 1, count($browser->elements('[data-field="heading"]')));

            $this->assertConsoleClean($browser, 'repeater');
        });
    }

    /**
     * The slug follows the title while it is untouched, mirroring the model's
     * own `creating` hook — so the admin sees the value that will be stored
     * rather than a blank box that fills in server-side.
     */
    public function test_the_slug_follows_the_title_on_a_new_page(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/pages/create')
                ->waitFor('[data-field="title"]', 10)
                ->type('[data-field="title"]', 'Chính sách đổi trả')
                ->pause(300)
                ->assertInputValue('[data-field="slug"]', 'chinh-sach-doi-tra');

            $this->assertConsoleClean($browser, 'tạo trang tĩnh');
        });
    }

    /**
     * The image field is not a text box and not a library of its own — it
     * opens the file manager (modules/Assets) in a popup iframe. Proving the
     * frame loads the real manager, and that closing it hands control back,
     * covers the part no feature test can: the postMessage round trip.
     */
    public function test_the_image_field_opens_the_file_manager(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/banners/create')
                ->waitFor('[data-media-picker]', 10)
                ->click('[data-media-picker]')
                ->waitFor('.shop-fm-overlay iframe[src*="/shop/media/picker"]', 10)
                ->withinFrame('.shop-fm-overlay iframe', function (Browser $frame): void {
                    $frame->waitFor('[data-fm="upload"]', 15)
                        ->assertPresent('[data-fm="choose"]')
                        ->click('[data-fm="close"]');
                })
                ->waitUntilMissing('.shop-fm-overlay', 5);

            $this->assertConsoleClean($browser, 'file manager');
        });
    }

    /**
     * The pick has to make it back across the frame. Every server-side test
     * was green while it did not: the chosen assets were Vue proxies, and
     * postMessage threw DataCloneError inside the iframe — the popup simply
     * stayed open. Only a real browser sees that.
     *
     * Still read-only: the form is filled, never saved.
     */
    public function test_a_file_chosen_in_the_manager_lands_in_the_field(): void
    {
        if (! Asset::query()->whereHas('file')->exists()) {
            $this->markTestSkipped('Thư viện media trên DB dev đang trống — không có gì để chọn.');
        }

        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/banners/create')
                ->waitFor('[data-media-picker]', 10)
                ->click('[data-media-picker]')
                ->waitFor('.shop-fm-overlay iframe', 10)
                ->withinFrame('.shop-fm-overlay iframe', function (Browser $frame): void {
                    $frame->waitFor('[data-fm-asset]', 15)->doubleClick('[data-fm-asset]');
                })
                ->waitUntilMissing('.shop-fm-overlay', 5)
                ->waitFor('[data-media-picker] img', 5);

            // Body copy: several files at once, inserted as <img> tags.
            $browser->visit('/panel/shop/pages/create')
                ->waitFor('[data-insert-image]', 10)
                ->click('[data-insert-image]')
                ->waitFor('.shop-fm-overlay iframe', 10)
                ->withinFrame('.shop-fm-overlay iframe', function (Browser $frame): void {
                    $frame->waitFor('[data-fm-asset]', 15)
                        ->click('[data-fm-asset]')
                        ->click('[data-fm="choose"]');
                })
                ->waitUntilMissing('.shop-fm-overlay', 5);

            $this->assertStringContainsString('<img src="', (string) $browser->value('textarea[data-field="content"]'));

            $this->assertConsoleClean($browser, 'chọn ảnh qua file manager');
        });
    }

    /**
     * "Photos by colour" rides on Lunar's product editor as a slot and draws
     * one group per colour — the part no feature test can see: the component
     * registered, mounted in the zone, and fetched its own state.
     */
    public function test_the_colour_photos_card_renders_on_the_product_editor(): void
    {
        $product = Product::query()
            ->whereHas('productOptions', fn ($query) => $query->whereIn('type', ['colour', 'swatch']))
            ->first();

        if (! $product) {
            $this->markTestSkipped('DB dev không có sản phẩm nào có tuỳ chọn màu.');
        }

        $this->browse(function (Browser $browser) use ($product): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit("/panel/products/{$product->id}/edit")
                ->waitFor('[data-colour-images] [data-colour]', 15);

            $this->assertGreaterThan(0, count($browser->elements('[data-colour-images] [data-colour]')));

            $this->assertConsoleClean($browser, 'ảnh theo màu');
        });
    }

    /**
     * Lunar's own gallery uploader (compiled into the vendor bundle) opens the
     * file manager instead of the file dialog, and what is chosen there reaches
     * Lunar's own upload request — nativeUploadBridge.js end to end.
     *
     * Read-only: the upload request is recorded and then cancelled in
     * `inertia:before`, so the product is never touched.
     */
    public function test_lunars_gallery_upload_goes_through_the_file_manager(): void
    {
        $product = Product::query()->first();

        if (! $product || ! Asset::query()->whereHas('file')->exists()) {
            $this->markTestSkipped('DB dev cần ít nhất một sản phẩm và một ảnh trong thư viện.');
        }

        $this->browse(function (Browser $browser) use ($product): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit("/panel/products/{$product->id}/edit")
                ->waitUntil('!!document.querySelector(\'input[type=file][accept="image/*"]\')', 15);

            $browser->script(<<<'JS'
                window.__uploads = [];
                document.addEventListener('inertia:before', (event) => {
                    const visit = event.detail.visit;
                    const data = visit.data instanceof FormData ? [...visit.data.values()] : Object.values(visit.data || {}).flat();
                    window.__uploads.push({ url: String(visit.url), files: data.filter((value) => value instanceof File).length });
                    event.preventDefault();
                });
                // Exactly what the gallery's Upload button does.
                document.querySelector('input[type=file][accept="image/*"]').click();
            JS);

            $browser->waitFor('.shop-fm-overlay iframe[src*="multiple=1"]', 10)
                ->withinFrame('.shop-fm-overlay iframe', function (Browser $frame): void {
                    $frame->waitFor('[data-fm-asset]', 15)
                        ->click('[data-fm-asset]')
                        ->click('[data-fm="choose"]');
                })
                ->waitUntilMissing('.shop-fm-overlay', 5)
                ->waitUntil('window.__uploads.length > 0', 10);

            $upload = $browser->script('return window.__uploads[0];')[0];

            $this->assertMatchesRegularExpression('#/products/\d+/media$#', $upload['url']);
            $this->assertSame(1, $upload['files']);

            $this->assertConsoleClean($browser, 'gallery sản phẩm qua file manager');
        });
    }
}
