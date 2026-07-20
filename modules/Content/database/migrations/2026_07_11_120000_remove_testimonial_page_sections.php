<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Remove the `testimonial` page section type — the section (Blade partial,
 * admin schema, seeder entry, SCSS/JS) has been dropped from the codebase.
 * Existing rows are deleted; the section-config cache for affected pages is
 * busted here because a raw DB delete does not fire the PageSection model
 * events that normally do that.
 *
 * down() is a no-op: the deleted rows only ever held demo defaults, and the
 * section type no longer exists to render them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $handles = DB::table('page_sections')
            ->where('type', 'testimonial')
            ->distinct()
            ->pluck('page_handle');

        DB::table('page_sections')->where('type', 'testimonial')->delete();

        foreach ($handles as $handle) {
            Cache::forget("content.sections.{$handle}");
        }
    }

    public function down(): void
    {
        // Intentionally empty — see class docblock.
    }
};
