<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
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
     * The image field is a picker, not a text box — the columns hold an Asset
     * id. Opening the library is the interaction that proves it.
     */
    public function test_the_image_field_opens_the_media_library(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->loginAs($this->staffId(), 'staff')
                ->visit('/panel/shop/banners/create')
                ->waitFor('[data-media-picker]', 10)
                ->click('[data-media-picker]')
                ->pause(800)
                ->assertPresent('[role="dialog"]');

            $this->assertConsoleClean($browser, 'bộ chọn ảnh');
        });
    }
}
