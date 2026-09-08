<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Enums\ProductOptionType;

/**
 * Hand our `display_type` over to Lunar 2.0, which shipped the same feature.
 *
 * We added a per-option display type in 1.x by subclassing `ProductOption` and
 * stashing the value in `meta->display_type` — there was no column for it and
 * `ModelManifest::replace()` made the accessor possible. Lunar 2.0 gives it a
 * real home: an indexed `type` column defaulting to `text`, a `ProductOptionType`
 * enum, and per-value payloads (`meta->colour`, a swatch media collection) that
 * `UpdateProductOption` keeps consistent when the type changes.
 *
 * So the extension goes away and the data moves onto the column. Names differ —
 * upstream spells them in en-GB and calls an image swatch a swatch:
 *
 *   text  → text     color → colour     image → swatch
 *
 * Idempotent: rows already carrying a valid `type` are left alone, and the meta
 * key is dropped only once it has been read.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    protected array $map = [
        'text' => 'text',
        'color' => 'colour',
        'image' => 'swatch',
    ];

    public function up(): void
    {
        $table = config('lunar.database.table_prefix').'product_options';

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'meta')) {
            return;
        }

        DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
            $meta = json_decode($row->meta ?? 'null', true);

            if (! is_array($meta) || ! array_key_exists('display_type', $meta)) {
                return;
            }

            $legacy = $meta['display_type'];
            unset($meta['display_type']);

            $update = ['meta' => $meta ? json_encode($meta) : null];

            // Only write `type` when the row is still on the default and we
            // actually recognise the legacy value: an option already edited in
            // the new admin is the more recent truth.
            if (($row->type ?? 'text') === 'text' && isset($this->map[$legacy])) {
                $update['type'] = $this->map[$legacy];
            }

            DB::table($table)->where('id', $row->id)->update($update);
        });
    }

    public function down(): void
    {
        $table = config('lunar.database.table_prefix').'product_options';

        if (! Schema::hasTable($table)) {
            return;
        }

        $back = array_flip($this->map);

        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $back) {
            $type = ProductOptionType::tryFrom($row->type ?? '')?->value;

            if ($type === null || ! isset($back[$type])) {
                return;
            }

            $meta = json_decode($row->meta ?? 'null', true);
            $meta = is_array($meta) ? $meta : [];
            $meta['display_type'] = $back[$type];

            DB::table($table)->where('id', $row->id)->update(['meta' => json_encode($meta)]);
        });
    }
};
