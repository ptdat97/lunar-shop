<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Lunar\Core\Auth\Manifest;
use Spatie\Permission\Models\Permission;

/**
 * The permission gating the shop's own content screens on the Lunar panel.
 *
 * It has to exist as a row, not just as a string in a Section: the panel's
 * `Gate::after` only grants an ability that the access-control manifest knows
 * about, and the manifest is built from the `permissions` table. Without this
 * row `can:content:manage` denies everyone — admins included.
 *
 * A migration rather than a seeder because a fresh install must end up with the
 * same permissions an upgraded one has; that asymmetry has bitten this upgrade
 * before (see docs/guides/upgrade-lunar-2.0.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = config('permission.table_names');

        if (! $tables || ! Schema::hasTable($tables['permissions'])) {
            return;
        }

        Permission::firstOrCreate([
            'name' => 'content:manage',
            'guard_name' => app(Manifest::class)->getAuthGuard(),
        ]);
    }

    public function down(): void
    {
        $tables = config('permission.table_names');

        if (! $tables || ! Schema::hasTable($tables['permissions'])) {
            return;
        }

        Permission::query()
            ->where('name', 'content:manage')
            ->where('guard_name', app(Manifest::class)->getAuthGuard())
            ->delete();
    }
};
