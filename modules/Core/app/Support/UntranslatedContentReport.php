<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lunar\Core\Enums\FieldTypeEnum;

/**
 * Finds catalog content that is missing a translation for a served locale
 * (roadmap §16).
 *
 * Why this needs to exist at all: a blank translation is silent. Lunar falls
 * back to another locale, so nothing throws and nothing logs — the shop simply
 * shows an English name on a Vietnamese page, or (before the 1.5 fix) an empty
 * label. Nobody notices until a customer does. See docs/upstream/README.md.
 *
 * Two families of translatable content, stored differently:
 *
 *   1. A `{locale: text}` JSON column — {"en": "Size", "vi": "Kích cỡ"}
 *   2. `attribute_data` — {"<attribute id>": {"en": …, "vi": …}}
 *
 * Only attributes of type `translated_text` are examined in family 2; a plain
 * Text field is not multilingual and its absence is not a gap.
 *
 * Lunar 2.0 moved content between the two families in both directions, so the
 * lists below are not the same as the 1.x ones:
 *  - `attributes.name` / `attribute_groups.name` became plain string columns —
 *    not translatable at all any more, so they left family 1.
 *  - spec 0018 promoted product / collection / brand `name`, `description` and
 *    `short_description` out of `attribute_data` into real JSON columns, so
 *    they moved from family 2 to family 1. `attribute_data` still holds
 *    everything else (meta_title, meta_description, custom fields).
 */
class UntranslatedContentReport
{
    /**
     * Translatable `{locale: text}` JSON columns, per table.
     *
     * @var array<string, list<string>>
     */
    private const TRANSLATABLE_COLUMNS = [
        'products' => ['name', 'description', 'short_description'],
        'collections' => ['name', 'description', 'short_description'],
        'brands' => ['description', 'short_description'],
        'product_options' => ['name', 'label'],
        'product_option_values' => ['name'],
    ];

    /**
     * Tables carrying `attribute_data`.
     *
     * `customers` is deliberately absent: its attribute_data holds personal
     * details, not copy anyone translates.
     */
    private const ATTRIBUTE_DATA_TABLES = [
        'products',
        'product_variants',
        'collections',
        'brands',
        'customer_groups',
    ];

    /** Rows read per query — keeps a large catalogue off the heap. */
    private const CHUNK = 500;

    /**
     * Locales the storefront actually serves. Auditing against locales the shop
     * does not offer would report gaps nobody can see.
     *
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys(config('theme.locales', []));
    }

    /**
     * @param  list<string>|null  $locales  defaults to every served locale
     * @return Collection<int, array{table: string, id: int, field: string, missing: list<string>, sample: string}>
     */
    public function run(?array $locales = null): Collection
    {
        $locales = $locales ?: $this->locales();

        if (count($locales) < 2) {
            return collect(); // single-locale shop: nothing to be missing
        }

        $prefix = config('lunar.database.table_prefix');

        return collect()
            ->merge($this->scanTranslatableColumns($prefix, $locales))
            ->merge($this->scanAttributeData($prefix, $locales))
            ->values();
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, array<string, mixed>>
     */
    private function scanTranslatableColumns(string $prefix, array $locales): Collection
    {
        $rows = collect();

        foreach (self::TRANSLATABLE_COLUMNS as $table => $columns) {
            DB::table($prefix.$table)
                ->select(['id', ...$columns])
                ->orderBy('id')
                ->chunk(self::CHUNK, function ($chunk) use ($rows, $table, $columns, $locales) {
                    foreach ($chunk as $row) {
                        foreach ($columns as $column) {
                            $values = $this->decode($row->{$column});

                            // A column left entirely empty is a blank field, not
                            // an untranslated one — there is nothing to translate.
                            if ($values === null || $values === []) {
                                continue;
                            }

                            $gap = $this->missing($values, $locales);

                            if ($gap['missing']) {
                                $rows->push([
                                    'table' => $table,
                                    'id' => (int) $row->id,
                                    'field' => $column,
                                    ...$gap,
                                ]);
                            }
                        }
                    }
                });
        }

        return $rows;
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, array<string, mixed>>
     */
    private function scanAttributeData(string $prefix, array $locales): Collection
    {
        $rows = collect();
        $translatable = $this->translatableAttributes($prefix);

        if ($translatable === []) {
            return $rows;
        }

        foreach (self::ATTRIBUTE_DATA_TABLES as $table) {
            DB::table($prefix.$table)
                ->select(['id', 'attribute_data'])
                ->orderBy('id')
                ->chunk(self::CHUNK, function ($chunk) use ($rows, $table, $locales, $translatable) {
                    foreach ($chunk as $row) {
                        $data = $this->decode($row->attribute_data);

                        if (! is_array($data)) {
                            continue;
                        }

                        foreach ($data as $attributeId => $values) {
                            $handle = $translatable[(int) $attributeId] ?? null;

                            if ($handle === null || ! is_array($values)) {
                                continue;
                            }

                            $gap = $this->missing($values, $locales);

                            if ($gap['missing']) {
                                $rows->push([
                                    'table' => $table,
                                    'id' => (int) $row->id,
                                    'field' => $handle,
                                    ...$gap,
                                ]);
                            }
                        }
                    }
                });
        }

        return $rows;
    }

    /**
     * Handles of every translated-text attribute, keyed by id.
     *
     * Lunar 2.0 (spec 0019) reshaped `attribute_data` from a handle-keyed
     * envelope carrying its own `field_type` (`{"meta_title": {field_type: …,
     * value: {…}}}`) to a raw id-keyed map (`{"7": {"en": …}}`). The row no
     * longer says what type it is or what it is called, so both now come from
     * the `attributes` table — and `attributes.type` is a `FieldTypeEnum` value
     * string rather than a class name.
     *
     * @return array<int, string>
     */
    private function translatableAttributes(string $prefix): array
    {
        return DB::table($prefix.'attributes')
            ->where('type', FieldTypeEnum::TranslatedText->value)
            ->pluck('handle', 'id')
            ->map(fn ($handle) => (string) $handle)
            ->all();
    }

    /**
     * Which locales have no usable value, plus one that does — the sample is
     * what the storefront falls back to, and it is how a human recognises the
     * record without opening the admin.
     *
     * A key that exists but holds an empty string counts as missing: that is the
     * exact shape that used to render a blank label.
     *
     * @param  array<string, mixed>  $values
     * @param  list<string>  $locales
     * @return array{missing: list<string>, sample: string}
     */
    private function missing(array $values, array $locales): array
    {
        $missing = [];

        foreach ($locales as $locale) {
            if (blank($values[$locale] ?? null)) {
                $missing[] = $locale;
            }
        }

        // Everything blank means an empty record, not a translation gap — there
        // is nothing to translate from and nothing rendered to notice.
        $sample = collect($values)->first(fn ($value) => filled($value));

        return $sample === null
            ? ['missing' => [], 'sample' => '']
            : ['missing' => $missing, 'sample' => (string) $sample];
    }

    /** @return array<string, mixed>|null */
    private function decode(mixed $raw): ?array
    {
        if (blank($raw)) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
