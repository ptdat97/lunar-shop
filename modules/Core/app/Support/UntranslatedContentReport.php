<?php

namespace Modules\Core\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
 *   1. A translatable JSON `name` column — {"en": "Size", "vi": "Kích cỡ"}
 *   2. `attribute_data` — {"name": {"value": {"en": …}, "field_type": …}}
 *
 * Only `field_type` ending in TranslatedText is examined in family 2; a plain
 * Text field is not multilingual and its absence is not a gap.
 */
class UntranslatedContentReport
{
    /** Tables whose `name` column is a translatable JSON map. */
    private const NAME_COLUMN_TABLES = [
        'product_options',
        'product_option_values',
        'attributes',
        'attribute_groups',
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
            ->merge($this->scanNameColumns($prefix, $locales))
            ->merge($this->scanAttributeData($prefix, $locales))
            ->values();
    }

    /**
     * @param  list<string>  $locales
     * @return Collection<int, array<string, mixed>>
     */
    private function scanNameColumns(string $prefix, array $locales): Collection
    {
        $rows = collect();

        foreach (self::NAME_COLUMN_TABLES as $table) {
            DB::table($prefix.$table)
                ->select(['id', 'name'])
                ->orderBy('id')
                ->chunk(self::CHUNK, function ($chunk) use ($rows, $table, $locales) {
                    foreach ($chunk as $row) {
                        $values = $this->decode($row->name);

                        if ($values === null) {
                            continue;
                        }

                        $gap = $this->missing($values, $locales);

                        if ($gap['missing']) {
                            $rows->push([
                                'table' => $table,
                                'id' => (int) $row->id,
                                'field' => 'name',
                                ...$gap,
                            ]);
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

        foreach (self::ATTRIBUTE_DATA_TABLES as $table) {
            DB::table($prefix.$table)
                ->select(['id', 'attribute_data'])
                ->orderBy('id')
                ->chunk(self::CHUNK, function ($chunk) use ($rows, $table, $locales) {
                    foreach ($chunk as $row) {
                        $data = $this->decode($row->attribute_data);

                        if (! is_array($data)) {
                            continue;
                        }

                        foreach ($data as $field => $definition) {
                            if (! $this->isTranslated($definition)) {
                                continue;
                            }

                            $gap = $this->missing($definition['value'], $locales);

                            if ($gap['missing']) {
                                $rows->push([
                                    'table' => $table,
                                    'id' => (int) $row->id,
                                    'field' => (string) $field,
                                    ...$gap,
                                ]);
                            }
                        }
                    }
                });
        }

        return $rows;
    }

    /** Only a TranslatedText field can be missing a locale. */
    private function isTranslated(mixed $definition): bool
    {
        return is_array($definition)
            && is_array($definition['value'] ?? null)
            && str_ends_with((string) ($definition['field_type'] ?? ''), 'TranslatedText');
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
