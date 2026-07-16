<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Product options now carry a display_type (text | color | image) in meta,
 * configured on the Product Options admin page — the variant builder and the
 * storefront picker no longer key swatch behaviour to the hardcoded 'color'
 * handle. Backfill: any option whose values already carry a swatch (hex in
 * meta.swatch or an image in the 'swatch' media collection) — in practice the
 * old seeded Color option — becomes display_type 'color' so existing swatches
 * keep rendering. Everything else defaults to 'text' via the model accessor.
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('lunar.database.table_prefix');

        $swatchOptionIds = DB::table("{$prefix}product_option_values")
            ->whereRaw("json_unquote(json_extract(meta, '$.swatch')) is not null")
            ->distinct()
            ->pluck('product_option_id');

        DB::table("{$prefix}product_options")
            ->where(function ($query) use ($swatchOptionIds) {
                $query->whereIn('id', $swatchOptionIds)
                    ->orWhere('handle', 'color');
            })
            ->orderBy('id')
            ->each(function (object $option) use ($prefix) {
                $meta = json_decode($option->meta ?? '{}', true) ?: [];

                if (($meta['display_type'] ?? null) === null) {
                    $meta['display_type'] = 'color';

                    DB::table("{$prefix}product_options")
                        ->where('id', $option->id)
                        ->update(['meta' => json_encode($meta)]);
                }
            });
    }

    public function down(): void
    {
        // display_type lives inside meta; leaving it in place is harmless for
        // older code (it never read that key), so no rollback needed.
    }
};
