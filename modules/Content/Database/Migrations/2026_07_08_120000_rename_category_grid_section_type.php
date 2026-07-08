<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the `category-grid` page section type to `collection-grid` so the type
 * key matches the data it renders (Lunar Collections) and the admin label.
 *
 * The section's settings shape also changed (auto `limit` → admin-curated
 * `items`); existing rows keep their `heading` but have no `items` yet, so the
 * section renders empty until an admin curates it (only demo data is affected).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('page_sections')
            ->where('type', 'category-grid')
            ->update(['type' => 'collection-grid']);
    }

    public function down(): void
    {
        DB::table('page_sections')
            ->where('type', 'collection-grid')
            ->update(['type' => 'category-grid']);
    }
};
