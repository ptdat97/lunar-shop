<?php

namespace Modules\Catalog\Support;

use Illuminate\Support\Str;

/**
 * A SQL expression that reads one locale out of a translatable JSON column.
 *
 * Lunar 2.0 (spec 0018) promoted `name`, `description` and `short_description`
 * off the `attribute_data` blob into real JSON columns holding a plain
 * `{locale: text}` map. Everything that sorted or matched on a product or
 * collection name in SQL was reaching into
 * `JSON_EXTRACT(attribute_data, '$.name.value')` and has to move with it.
 *
 * The move is also a fix. The old expression returned whatever sat under
 * `value` — a bare string for a `Text` field but the whole `{"en": …, "vi": …}`
 * object for a `TranslatedText` one — so an A–Z sort over a mixed catalogue was
 * really sorting JSON punctuation, and a name search never matched a translated
 * product unless the term happened to appear in the encoded object. This reads
 * the requested locale and falls back to whichever translation exists, matching
 * what `translate()` shows the visitor.
 */
class TranslatedColumn
{
    /**
     * @param  string  $column  qualified column, e.g. `lunar_products.name`
     * @param  string|null  $locale  defaults to the current app locale
     */
    public static function sql(string $column, ?string $locale = null): string
    {
        // Locale reaches this from app()->getLocale() / an Accept-Language
        // header, so it is interpolated only after being reduced to the shape a
        // locale can actually take. It cannot be a binding: these expressions go
        // through orderByRaw, where bindings are not carried.
        $locale = Str::of($locale ?: app()->getLocale())
            ->replaceMatches('/[^A-Za-z0-9_-]/', '')
            ->value();

        $path = '$."'.$locale.'"';

        return sprintf(
            "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(%s, '%s')), JSON_UNQUOTE(JSON_EXTRACT(JSON_EXTRACT(%s, '$.*'), '$[0]')))",
            $column,
            $path,
            $column,
        );
    }
}
