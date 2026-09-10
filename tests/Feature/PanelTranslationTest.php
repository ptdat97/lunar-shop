<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Vietnamese panel overrides have to stay structurally identical to what
 * Lunar ships, not just look right.
 *
 * Lunar's panel already carries a `vi` locale, but 1302 of its 1994 keys were
 * still the English string verbatim — placeholders, not translations. Filling
 * them in is bulk work, and bulk work is where a `{amount}` quietly becomes
 * `{sotien}`: the page then renders the literal brace text to a member of staff
 * and nothing anywhere errors.
 *
 * These placeholders are vue-i18n's `{...}`, resolved in the browser, so a
 * server-side test is the only place this can be caught at all.
 */
class PanelTranslationTest extends TestCase
{
    private const OVERRIDE_DIR = 'lang/vendor/panel/vi';

    private const VENDOR_DIR = 'vendor/lunarphp/panel/resources/lang';

    /** @return array<string, string> */
    private function flatten(array $lines, string $prefix = ''): array
    {
        $out = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $out += $this->flatten($value, $path);

                continue;
            }

            $out[$path] = (string) $value;
        }

        return $out;
    }

    /** @return array<int, string> */
    private function overrideFiles(): array
    {
        return glob(base_path(self::OVERRIDE_DIR).'/*.php') ?: [];
    }

    public function test_there_is_something_to_check(): void
    {
        $this->assertNotEmpty(
            $this->overrideFiles(),
            'Không tìm thấy file dịch nào — đường dẫn override có thể đã sai.',
        );
    }

    /** An override key that Lunar does not have is a typo that translates nothing. */
    public function test_every_overridden_key_exists_upstream(): void
    {
        foreach ($this->overrideFiles() as $file) {
            $group = basename($file, '.php');
            $english = base_path(self::VENDOR_DIR."/en/{$group}.php");

            $this->assertFileExists($english, "Ghi đè nhóm [{$group}] mà Lunar không có nhóm này.");

            $ours = $this->flatten(require $file);
            $theirs = $this->flatten(require $english);

            $unknown = array_diff_key($ours, $theirs);

            $this->assertSame(
                [],
                array_keys($unknown),
                "Nhóm [{$group}] có khoá không tồn tại ở upstream — gõ sai thì không dịch được gì.",
            );
        }
    }

    /**
     * Placeholders and plural forms must survive translation.
     *
     * `{count} item|{count} items` has two forms; Vietnamese does not inflect,
     * so both stay the same text — but there must still be two, or vue-i18n
     * picks the wrong branch and renders the raw pluralisation string.
     */
    public function test_placeholders_and_plural_forms_survive(): void
    {
        foreach ($this->overrideFiles() as $file) {
            $group = basename($file, '.php');

            $ours = $this->flatten(require $file);
            $theirs = $this->flatten(require base_path(self::VENDOR_DIR."/en/{$group}.php"));

            foreach ($ours as $key => $translated) {
                $original = $theirs[$key] ?? null;

                if ($original === null) {
                    continue;
                }

                preg_match_all('/\{[a-zA-Z0-9_]+\}/', $original, $expected);
                preg_match_all('/\{[a-zA-Z0-9_]+\}/', $translated, $actual);

                sort($expected[0]);
                sort($actual[0]);

                $this->assertSame(
                    $expected[0],
                    $actual[0],
                    "[{$group}.{$key}] đổi placeholder: bản gốc [{$original}] → bản dịch [{$translated}]. "
                        .'Đây là placeholder của vue-i18n, đổi tên là trang in ra chữ trong ngoặc.',
                );

                $this->assertSame(
                    substr_count($original, '|'),
                    substr_count($translated, '|'),
                    "[{$group}.{$key}] lệch số dạng số nhiều — vue-i18n sẽ chọn nhầm nhánh.",
                );
            }
        }
    }

    /** A translation left as the English string is an unfinished job, not an override. */
    public function test_no_override_is_still_english(): void
    {
        $untouched = [];

        foreach ($this->overrideFiles() as $file) {
            $group = basename($file, '.php');

            $ours = $this->flatten(require $file);
            $theirs = $this->flatten(require base_path(self::VENDOR_DIR."/en/{$group}.php"));

            foreach ($ours as $key => $translated) {
                if (($theirs[$key] ?? null) !== $translated) {
                    continue;
                }

                // Placeholders carry Latin letters that are not words —
                // `{unit}³` has nothing in it to translate. Strip them before
                // asking whether any prose is left.
                $prose = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $translated);

                // Words that are the same in both languages are fine to repeat.
                if (! preg_match('/\p{Latin}/u', $prose) || $this->isProperNoun($translated)) {
                    continue;
                }

                $untouched[] = "{$group}.{$key} = {$translated}";
            }
        }

        $this->assertSame([], $untouched, 'Có khoá được ghi đè nhưng vẫn để nguyên tiếng Anh.');
    }

    private function isProperNoun(string $value): bool
    {
        return in_array($value, [
            'Email', 'SKU', 'GTIN', 'MPN', 'EAN', 'URL', 'API', 'ID', 'PDF', 'CSV',
            'VNPay', 'MoMo', 'Facebook', 'Instagram', 'Zalo', 'Webhook', 'Slug',
            'YouTube', 'Vimeo', 'TikTok', 'WebP', 'Laravel', 'Horizon',
            'ISO-2', 'ISO-3', 'ISO 4217', 'HTML', 'JSON', 'SMTP',
        ], true);
    }
}
