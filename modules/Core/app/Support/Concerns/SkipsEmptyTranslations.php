<?php

namespace Modules\Core\Support\Concerns;

use Illuminate\Support\Arr;

/**
 * Makes `translate()` skip a locale whose value is present but empty.
 *
 * Lunar's `HasTranslations::translate()` resolves a locale with
 * `Arr::get($values, $locale, $default)`. When the key exists but holds an empty
 * value, `Arr::get()` returns that empty value rather than the default, so the
 * fallback never runs. A product option named only in English but carrying a
 * blank `vi` key therefore renders as an empty label everywhere the shop runs
 * under `vi` — the storefront option picker and the admin variant builder both.
 *
 * This is the fix that used to live in
 * `patches/lunar-core-translations-locale-fallback.patch`. A composer patch is
 * the last rung of the extension ladder (docs/README.md §1) and it makes
 * `composer update` hard-fail whenever upstream touches the method, so it is
 * applied through `ModelManifest::replace()` instead — see
 * CatalogServiceProvider.
 *
 * Scope: only the four models whose `name` is a translatable JSON column
 * (`lunar_product_options`, `lunar_product_option_values`, `lunar_attributes`,
 * `lunar_attribute_groups`). Everything else — Product, Brand, Customer,
 * ProductVariant — carries its names in `attribute_data` and reads them through
 * `translateAttribute()`, which already skips blank field values upstream.
 *
 * Behaviour matches Lunar's `translateAttribute()`: prefer the requested locale,
 * then the app locale, then any locale that actually has a value.
 */
trait SkipsEmptyTranslations
{
    /**
     * @param  string  $attribute
     * @param  string|null  $locale
     * @return mixed
     */
    public function translate($attribute, $locale = null)
    {
        $values = $this->getAttribute($attribute);

        if (is_string($values)) {
            return $values;
        }

        if (! $values) {
            return null;
        }

        // Convert up front. Lunar's version only did this for the first lookup,
        // leaving the fallback to call Arr::get() on a stdClass.
        $values = Arr::accessible($values) ? $values : get_object_vars($values);

        foreach ([$locale ?: app()->getLocale(), app()->getLocale()] as $preferred) {
            $value = Arr::get($values, $preferred);

            if (filled($value)) {
                return $value;
            }
        }

        return Arr::first($values, fn ($value) => filled($value));
    }
}
