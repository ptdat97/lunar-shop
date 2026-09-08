<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Casts\ArrayObject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Models\Brand;
use Lunar\Core\Models\Collection as LunarCollection;
use Lunar\Core\Models\Product;
use Lunar\Core\Models\ProductOption;
use Lunar\Core\Models\ProductOptionValue;
use Modules\Core\Casts\FilledTranslations;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Lunar's `HasTranslations::translate()` resolves a locale with
 * `Arr::get($values, $locale, $default)`. When the key exists but holds an empty
 * value, `Arr::get()` returns that empty value rather than the default, so the
 * fallback never runs — a shop running under `vi` shows blank labels for
 * content that was only ever filled in English. Still true in 2.0.0-alpha.6.
 *
 * The fix used to be a composer patch, then a `translate()` override carried by
 * four subclasses registered through `ModelManifest::replace()`. **2.0 removed
 * model replacement**, so it now lives one layer down, in the column: the
 * {@see FilledTranslations} cast drops blank locales as the JSON is decoded,
 * which makes upstream's `translate()` correct as written because "key exists
 * but is empty" can no longer happen. Registered in CatalogServiceProvider via
 * `Model::addCasts()`.
 *
 * Mutation check: remove a column from `guardEmptyTranslations()` and its case
 * here goes red.
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
     * Every `{locale: text}` column read through `translate()`.
     *
     * The surface moved in both directions with 2.0: `lunar_attributes.name`
     * and `lunar_attribute_groups.name` are plain `string` columns now and drop
     * off the list, while spec 0018 promoted product / collection / brand
     * `name` and `description` out of `attribute_data` into real JSON columns
     * and adds them to it.
     *
     * @return array<string, array{class-string, string}>
     */
    public static function translatableColumns(): array
    {
        return [
            'product name' => [Product::class, 'name'],
            'product description' => [Product::class, 'description'],
            'product short description' => [Product::class, 'short_description'],
            'collection name' => [LunarCollection::class, 'name'],
            'collection description' => [LunarCollection::class, 'description'],
            'brand description' => [Brand::class, 'description'],
            'product option name' => [ProductOption::class, 'name'],
            'product option label' => [ProductOption::class, 'label'],
            'product option value name' => [ProductOptionValue::class, 'name'],
        ];
    }

    #[DataProvider('translatableColumns')]
    public function test_a_blank_current_locale_falls_back_to_a_filled_one(string $model, string $column): void
    {
        $instance = new $model;
        $instance->setRawAttributes([$column => json_encode(['en' => 'Size', 'vi' => ''])], sync: true);

        // Without the fix this returns '' — the `vi` key exists, so Arr::get()
        // hands back the empty string instead of falling through.
        $this->assertSame('Size', $instance->translate($column));
        $this->assertSame('Size', $instance->translate($column, 'vi'));
    }

    /**
     * The container each model already declared has to survive the swap:
     * catalogue models cast these columns to `AsCollection`, the option models
     * to `AsArrayObject`, and admin forms can tell the difference.
     *
     * @return array<string, array{class-string, string, class-string}>
     */
    public static function containerShapes(): array
    {
        return [
            'product name is a Collection' => [Product::class, 'name', Collection::class],
            'collection name is a Collection' => [LunarCollection::class, 'name', Collection::class],
            'option name is an ArrayObject' => [ProductOption::class, 'name', ArrayObject::class],
            'option value name is an ArrayObject' => [ProductOptionValue::class, 'name', ArrayObject::class],
        ];
    }

    #[DataProvider('containerShapes')]
    public function test_the_cast_keeps_the_container_the_model_declared(string $model, string $column, string $expected): void
    {
        $instance = new $model;
        $instance->setRawAttributes([$column => json_encode(['en' => 'Size'])], sync: true);

        $this->assertInstanceOf($expected, $instance->{$column});
    }

    /** Blank locales are not merely skipped on read — they are not there at all. */
    public function test_a_blank_locale_is_absent_from_the_decoded_value(): void
    {
        $option = new ProductOption;
        $option->setRawAttributes(['name' => json_encode(['en' => 'Size', 'vi' => ''])], sync: true);

        $this->assertSame(['en' => 'Size'], $option->name->getArrayCopy());
    }

    public function test_a_filled_current_locale_still_wins(): void
    {
        $option = new ProductOption;
        $option->setRawAttributes(['name' => json_encode(['en' => 'Size', 'vi' => 'Kích cỡ'])], sync: true);

        $this->assertSame('Kích cỡ', $option->translate('name'));
        $this->assertSame('Size', $option->translate('name', 'en'));
    }

    public function test_an_all_blank_set_translates_to_null(): void
    {
        $option = new ProductOption;
        $option->setRawAttributes(['name' => json_encode(['en' => '', 'vi' => ''])], sync: true);

        $this->assertNull($option->translate('name'));
    }

    /** A plain string column is returned untouched, as upstream does. */
    public function test_a_non_translatable_value_is_returned_as_is(): void
    {
        $option = new ProductOption;
        $option->setRawAttributes(['handle' => 'size'], sync: true);

        $this->assertSame('size', $option->translate('handle'));
    }

    /**
     * 2.0 made `lunar_attributes.name` and `lunar_attribute_groups.name` plain
     * string columns, which is why they left the list above. If upstream ever
     * makes them translatable again they need the cast too — this is what says
     * so out loud.
     */
    public function test_attribute_names_are_no_longer_translatable_columns(): void
    {
        foreach (['attributes', 'attribute_groups'] as $table) {
            $type = Schema::getColumnType(config('lunar.database.table_prefix').$table, 'name');

            $this->assertNotSame('json', $type, "lunar_{$table}.name is JSON again — it needs FilledTranslations.");
        }
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
