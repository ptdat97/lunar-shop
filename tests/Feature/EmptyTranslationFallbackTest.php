<?php

namespace Tests\Feature;

use Lunar\Models\Attribute;
use Lunar\Models\AttributeGroup;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Modules\Core\Support\Concerns\SkipsEmptyTranslations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lunar's `HasTranslations::translate()` resolves a locale with
 * `Arr::get($values, $locale, $default)`. When the key exists but holds an empty
 * value, `Arr::get()` returns that empty value rather than the default, so the
 * fallback never runs — a shop running under `vi` shows blank labels for options
 * that were only ever named in English.
 *
 * The fix used to be a composer patch on lunarphp/core. It now rides on
 * `ModelManifest::replace()` via {@see SkipsEmptyTranslations}, so these tests
 * are what keeps `patches/` from having to come back.
 *
 * Mutation check: drop `use SkipsEmptyTranslations` from any of the four models
 * in modules/Catalog/app/Models and its case here goes red.
 */
class EmptyTranslationFallbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The shop's real situation: app runs in Vietnamese, some catalog
        // metadata was only ever filled in English.
        app()->setLocale('vi');
    }

    /**
     * The four models whose `name` is a translatable JSON column — the entire
     * surface `translate()` covers. Everything else reads names out of
     * `attribute_data` through `translateAttribute()`, which already skips blanks.
     *
     * @return array<string, array{class-string}>
     */
    public static function translatableModels(): array
    {
        return [
            'product option' => [ProductOption::class],
            'product option value' => [ProductOptionValue::class],
            'attribute' => [Attribute::class],
            'attribute group' => [AttributeGroup::class],
        ];
    }

    #[DataProvider('translatableModels')]
    public function test_a_blank_current_locale_falls_back_to_a_filled_one(string $model): void
    {
        $instance = new ($model::modelClass());
        $instance->setRawAttributes(['name' => json_encode(['en' => 'Size', 'vi' => ''])], sync: true);

        // Without the fix this returns '' — the `vi` key exists, so Arr::get()
        // hands back the empty string instead of falling through.
        $this->assertSame('Size', $instance->translate('name'));
        $this->assertSame('Size', $instance->translate('name', 'vi'));
    }

    #[DataProvider('translatableModels')]
    public function test_every_translatable_model_is_swapped_for_ours(string $model): void
    {
        $resolved = $model::modelClass();

        $this->assertStringStartsWith('Modules\\', $resolved);
        $this->assertContains(
            SkipsEmptyTranslations::class,
            class_uses_recursive($resolved),
            "{$resolved} is registered but does not carry the translation fix.",
        );
    }

    public function test_a_filled_current_locale_still_wins(): void
    {
        $option = new (ProductOption::modelClass());
        $option->setRawAttributes(['name' => json_encode(['en' => 'Size', 'vi' => 'Kích cỡ'])], sync: true);

        $this->assertSame('Kích cỡ', $option->translate('name'));
        $this->assertSame('Size', $option->translate('name', 'en'));
    }

    public function test_an_all_blank_set_translates_to_null(): void
    {
        $option = new (ProductOption::modelClass());
        $option->setRawAttributes(['name' => json_encode(['en' => '', 'vi' => ''])], sync: true);

        $this->assertNull($option->translate('name'));
    }

    /** A plain string column is returned untouched, as upstream does. */
    public function test_a_non_translatable_value_is_returned_as_is(): void
    {
        $option = new (ProductOption::modelClass());
        $option->setRawAttributes(['handle' => 'size'], sync: true);

        $this->assertSame('size', $option->translate('handle'));
    }

    /**
     * The composer patch this replaced is gone for good — if it comes back the
     * two fixes stack and `composer update` starts failing on upstream edits.
     */
    public function test_the_composer_patch_is_not_reintroduced(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertArrayNotHasKey('patches', $composer['extra'] ?? []);
        $this->assertArrayNotHasKey('cweagans/composer-patches', $composer['require'] ?? []);
        $this->assertDirectoryDoesNotExist(base_path('patches'));
    }
}
