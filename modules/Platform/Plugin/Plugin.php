<?php

namespace Modules\Platform\Plugin;

use Illuminate\Contracts\Foundation\Application;
use Modules\Platform\Services\HookManager;

/**
 * Contract every plugin implements. A plugin is a self-contained extension —
 * routes, migrations, views, Filament pages, hooks — that adds to a single shop
 * deployment without editing core. It's an INTERNAL extension SDK for trusted
 * (first-party / partner) developers: plugins run with full app privileges, so
 * they're gated by the `config/plugins.php` allow-list, not sandboxed.
 *
 * Loaded by {@see PluginManager} after the core modules, in dependency order.
 *
 * The lifecycle methods (install/activate/deactivate/uninstall) are part of the
 * stable contract but are driven by artisan commands in E2 — they are NOT called
 * during register()/boot(). {@see BasePlugin} provides no-op defaults so most
 * plugins only implement id()/version()/register()/boot().
 */
interface Plugin
{
    /** Unique, stable identifier, e.g. "acme/loyalty". */
    public function id(): string;

    /** Plugin version (semver). */
    public function version(): string;

    /**
     * Version constraints this plugin needs to load. Keyed by dependency id;
     * the special key `core` is the app version (config `plugins.core_version`).
     * e.g. ['core' => '^1.0', 'acme/points' => '^2.0'].
     *
     * @return array<string, string>
     */
    public function requires(): array;

    /** Register-phase: bind services into the container. */
    public function register(Application $app): void;

    /** Boot-phase: add actions/filters to Hooks::*, load routes/views, etc. */
    public function boot(HookManager $hooks): void;

    // ─── Lifecycle (artisan-driven, idempotent — see E2) ─────────────────────

    /** First install: run migrations, seed default config. */
    public function install(): void;

    /** Activate: publish assets, warm caches. */
    public function activate(): void;

    /** Deactivate: cancel schedules; keep data. */
    public function deactivate(): void;

    /** Uninstall: roll back migrations / remove data (confirmed). */
    public function uninstall(): void;
}
