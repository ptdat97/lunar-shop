<?php

namespace Tests\Browser;

use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;
use Lunar\Admin\Models\Staff;
use Tests\DuskTestCase;

/**
 * Drives a real browser at the SKU variants page, because this bug only exists
 * in one.
 *
 * Every server-side probe came back clean — repeater rows are keyed by UUID,
 * the keys survive Livewire round-trips, `generateCombinations()` and mounting
 * the media action, and the rendered HTML always matched the component state
 * (12 rows, 12 UUIDs, no drift). The failure is entirely in the browser's DOM
 * lifecycle:
 *
 *   Livewire Entangle Error: Livewire property
 *   ['data.skus.<uuid>.status'] cannot be found on component
 *
 *   onMutate → initTree → support.js (await) → initTree
 *     → initInterceptors → isLive → THROW
 *
 * So the assertion that matters is not "did the request succeed" but "did the
 * browser console stay clean while the modal was inserted".
 *
 * Runs against the real site (APP_URL) and the development database — the same
 * product, the same 12 SKUs that reproduce it by hand. It only reads and opens
 * a modal; nothing is saved.
 */
class VariantMediaPickerTest extends DuskTestCase
{
    /** The product reported as broken. */
    private const PRODUCT_ID = 1;

    /**
     * Console entries that are noise rather than failure.
     *
     * The preload hint is a browser heuristic about a stylesheet that IS linked
     * and applied — already verified separately.
     */
    private const IGNORED = [
        'was preloaded using link preload but not used',
        'Download the React DevTools',
        'favicon',
    ];

    /**
     * The asset ids currently shown as thumbnails on one SKU row.
     *
     * Read off `wire:key` (`…skus.<row>.images-<assetId>`) via hasAttribute
     * rather than a CSS selector — the colon needs escaping that does not
     * survive the trip into the browser.
     *
     * @return array<int, string>
     */
    private function rowImageKeys(Browser $browser, string $rowKey): array
    {
        $keys = $browser->driver->executeScript(<<<'JS'
            const needle = 'skus.' + arguments[0] + '.images-';
            return [...document.querySelectorAll('*')]
                .map(e => e.getAttribute('wire:key'))
                .filter(k => k && k.includes(needle))
                .sort();
        JS, [$rowKey]);

        return $keys ?: [];
    }

    /**
     * @return array<int, string>
     */
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

    private function assertConsoleClean(Browser $browser, string $stage): void
    {
        $errors = $this->consoleErrors($browser);

        $this->assertSame(
            [],
            $errors,
            "Browser console was not clean {$stage}:\n  - ".implode("\n  - ", $errors),
        );
    }

    public function test_the_variants_page_loads_without_console_errors(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(Staff::findOrFail(1), 'staff')
                ->visit('/lunar/products/'.self::PRODUCT_ID.'/variants')
                ->waitForText('SKU', 15)
                ->pause(1500); // let the lazily x-loaded components settle

            $this->assertConsoleClean($browser, 'on page load');
        });
    }

    /**
     * The reported action: click the library button on a SKU row. The modal is
     * inserted, Filament initialises the new subtree, and that is where the
     * entangle error fired.
     */
    public function test_opening_the_media_picker_keeps_the_console_clean(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(Staff::findOrFail(1), 'staff')
                ->visit('/lunar/products/'.self::PRODUCT_ID.'/variants')
                ->waitForText('SKU', 15)
                ->pause(1500);

            // Drain whatever the page load produced so the assertion below is
            // about the modal, not about anything before it.
            $this->consoleErrors($browser);

            $buttons = $browser->driver->findElements(
                WebDriverBy::cssSelector('button[wire\\:click*="browseLibrary"], [x-on\\:click*="browseLibrary"]')
            );

            $this->assertNotEmpty($buttons, 'No "browse library" button found on any SKU row.');

            // Click through JS: the dev debugbar floats over the bottom of the
            // page and intercepts a real click on the last rows.
            $browser->driver->executeScript(
                'arguments[0].scrollIntoView({block: "center"}); arguments[0].click();',
                [$buttons[0]],
            );

            $browser->pause(2500); // modal insert + async x-load init

            $errors = $this->consoleErrors($browser);

            if ($errors) {
                fwrite(STDERR, "\n=== CONSOLE ===\n".implode("\n", $errors)."\n");
            }

            $this->assertSame(
                [],
                $errors,
                "Browser console was not clean after opening the media picker:\n  - "
                .implode("\n  - ", $errors),
            );
        });
    }

    /** And the modal actually appears, rather than failing silently. */
    public function test_the_media_picker_modal_opens(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(Staff::findOrFail(1), 'staff')
                ->visit('/lunar/products/'.self::PRODUCT_ID.'/variants')
                ->waitForText('SKU', 15)
                ->pause(1500);

            $buttons = $browser->driver->findElements(
                WebDriverBy::cssSelector('button[wire\\:click*="browseLibrary"], [x-on\\:click*="browseLibrary"]')
            );

            $this->assertNotEmpty($buttons, 'No "browse library" button found on any SKU row.');

            $browser->driver->executeScript(
                'arguments[0].scrollIntoView({block: "center"}); arguments[0].click();',
                [$buttons[0]],
            );

            $browser->waitFor('.fi-modal-window', 15)
                ->assertVisible('.fi-modal-window');
        });
    }

    /**
     * The complaint itself, end to end: open the library on a SKU row, click a
     * picture, confirm — and check the row actually took it.
     *
     * Everything above stops at "the modal opened", which is not what was
     * reported. This is the workflow that was said to do nothing.
     */
    public function test_choosing_an_image_attaches_it_to_the_row(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(Staff::findOrFail(1), 'staff')
                ->visit('/lunar/products/'.self::PRODUCT_ID.'/variants')
                ->waitForText('SKU', 15)
                ->pause(1500);

            $buttons = $browser->driver->findElements(
                WebDriverBy::cssSelector('button[wire\\:click*="browseLibrary"], [x-on\\:click*="browseLibrary"]')
            );

            $this->assertNotEmpty($buttons, 'No "browse library" button found on any SKU row.');

            // Which row this button belongs to — the assertion at the end has to
            // look at that row and no other.
            $statePath = $buttons[0]->getAttribute('wire:click') ?? '';
            preg_match('/skus\.([0-9a-f-]{36})\.images/i', $statePath, $m);
            $rowKey = $m[1] ?? null;

            $this->assertNotNull($rowKey, "Could not read the row key from: {$statePath}");

            $before = $this->rowImageKeys($browser, $rowKey);

            $browser->driver->executeScript(
                'arguments[0].scrollIntoView({block: "center"}); arguments[0].click();',
                [$buttons[0]],
            );

            $browser->waitFor('.fi-modal-window', 15)->pause(1200);

            $tiles = $browser->driver->findElements(
                WebDriverBy::cssSelector('.fi-modal-window [wire\\:click*="toggle"][role="button"]')
            );

            $this->assertNotEmpty($tiles, 'The library modal rendered no selectable pictures.');

            $countSelected = fn () => (int) $browser->driver->executeScript(
                'return document.querySelectorAll(".fi-modal-window .ring-2").length;'
            );

            fwrite(STDERR, "\n    ô được chọn khi vừa mở modal: ".$countSelected().' / '.count($tiles)."\n");

            $browser->driver->executeScript('arguments[0].click();', [$tiles[0]]);
            $browser->pause(1200);

            fwrite(STDERR, '    ô được chọn sau khi bấm 1 ô : '.$countSelected()."\n");

            // Confirm — the modal's submit action writes the ids back to the field.
            $browser->click('.fi-modal-window .fi-modal-footer-actions button')
                ->waitUntilMissing('.fi-modal-window', 15)
                ->pause(1500);

            $errors = $this->consoleErrors($browser);

            if ($errors) {
                fwrite(STDERR, "\n=== CONSOLE ===\n".implode("\n", $errors)."\n");
            }

            // What the user actually looks at: the thumbnails on that row. If the
            // pick never reached the state, the row re-renders unchanged.
            $after = $this->rowImageKeys($browser, $rowKey);

            fwrite(STDERR, "\n=== dòng {$rowKey}\n    trước: ".json_encode($before)."\n    sau  : ".json_encode($after)."\n");

            $this->assertNotSame(
                $before,
                $after,
                'Choosing a picture changed nothing on the row — the pick never reached the state.',
            );

            // Adding, not replacing. The modal has to open with the row's current
            // pictures already selected; when it did not, confirming wiped them
            // and left only the newly clicked one.
            $this->assertSame(
                $before,
                array_values(array_intersect($after, $before)),
                'The row lost pictures it already had — the modal opened without them selected.',
            );

            $this->assertCount(
                count($before) + 1,
                $after,
                'Exactly one picture should have been added.',
            );
        });
    }
}
