<?php

namespace Modules\Hook\Plugin;

use Composer\Semver\Semver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Modules\Hook\Services\HookManager;
use Throwable;

/**
 * Discovers, validates, and boots plugins — the runtime half of the internal
 * Plugin SDK. Runs AFTER the core modules (so plugins hook into a fully-wired
 * app) and is deliberately fail-soft: a broken/incompatible plugin is logged
 * and skipped, never allowed to crash the app.
 *
 * Pipeline:
 *   1. discover()   — find candidate plugins (composer `extra.lunar-sme.plugin`
 *                     + `config('plugins.paths')` folders with plugin.json).
 *   2. allow-list   — keep only ids in `config('plugins.enabled')`.
 *   3. order()      — topological sort by `requires` (deps before dependents).
 *   4. validate     — every requirement present + version satisfied (semver);
 *                     `core` checked against `config('plugins.core_version')`.
 *   5. register()/boot() each surviving plugin, in order.
 */
class PluginManager
{
    /** @var array<string, Plugin> resolved, enabled, validated plugins (id => instance) */
    protected array $booted = [];

    public function __construct(
        protected Application $app,
    ) {}

    /**
     * Resolve the load order and register every enabled, satisfiable plugin.
     * Call from a service provider's register() (after core modules).
     *
     * @return list<string> ids that were loaded, in load order
     */
    public function load(): array
    {
        $enabled = (array) config('plugins.enabled', []);

        $candidates = collect($this->discover())
            ->filter(fn (Plugin $p) => in_array($p->id(), $enabled, true))
            ->keyBy(fn (Plugin $p) => $p->id());

        $ordered = $this->order($candidates->all());

        $loaded = [];
        $available = []; // id => version, of plugins already loaded (for dep checks)
        $available['core'] = (string) config('plugins.core_version', '0.0.0');

        foreach ($ordered as $plugin) {
            if (! $this->satisfied($plugin, $available)) {
                continue; // satisfied() logs the reason
            }

            try {
                $plugin->register($this->app);
                $this->booted[$plugin->id()] = $plugin;
                $loaded[] = $plugin->id();
                $available[$plugin->id()] = $plugin->version();
            } catch (Throwable $e) {
                Log::warning("Plugin [{$plugin->id()}] failed to register and was skipped.", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $loaded;
    }

    /**
     * Boot every plugin registered by load(). Call from boot() of the provider.
     */
    public function boot(): void
    {
        $hooks = $this->app->make(HookManager::class);

        foreach ($this->booted as $plugin) {
            try {
                $plugin->boot($hooks);
            } catch (Throwable $e) {
                Log::warning("Plugin [{$plugin->id()}] failed to boot.", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return array<string, Plugin> id => instance, of plugins that loaded */
    public function loaded(): array
    {
        return $this->booted;
    }

    /**
     * Enabled, config-providing plugins resolved on demand (independent of the
     * boot-time load) — so the admin page reflects the CURRENT allow-list.
     *
     * @return array<string, \Modules\Hook\Plugin\PluginConfig>
     */
    public function configPlugins(): array
    {
        $enabled = (array) config('plugins.enabled', []);
        $plugins = [];

        foreach ($this->discover() as $plugin) {
            if (in_array($plugin->id(), $enabled, true) && $plugin instanceof PluginConfig) {
                $plugins[$plugin->id()] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * Find a single discovered plugin by id — regardless of the allow-list, so
     * the CLI can install a plugin before it's enabled. Null if not present.
     */
    public function resolve(string $id): ?Plugin
    {
        foreach ($this->discover() as $plugin) {
            if ($plugin->id() === $id) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Static health check (no side effects): for every ENABLED plugin, report
     * requirements that aren't met — missing dependency, version mismatch, or a
     * required plugin that isn't itself enabled. Empty result = all healthy.
     *
     * @return list<array{plugin:string, issue:string}>
     */
    public function diagnose(): array
    {
        $enabled = (array) config('plugins.enabled', []);
        $discovered = collect($this->discover())->keyBy(fn (Plugin $p) => $p->id());

        $available = ['core' => (string) config('plugins.core_version', '0.0.0')];
        foreach ($enabled as $id) {
            if ($plugin = $discovered->get($id)) {
                $available[$id] = $plugin->version();
            }
        }

        $issues = [];

        foreach ($enabled as $id) {
            $plugin = $discovered->get($id);

            if (! $plugin) {
                $issues[] = ['plugin' => $id, 'issue' => 'enabled but not discoverable on disk'];

                continue;
            }

            foreach ($plugin->requires() as $dep => $constraint) {
                $version = $available[$dep] ?? null;

                if ($version === null) {
                    $issues[] = ['plugin' => $id, 'issue' => "requires [{$dep}] which is not available/enabled"];
                } elseif (! Semver::satisfies($version, $constraint)) {
                    $issues[] = ['plugin' => $id, 'issue' => "requires [{$dep} {$constraint}] but [{$version}] is present"];
                }
            }
        }

        return $issues;
    }

    // ─── Lifecycle orchestration (E2 — artisan-driven, idempotent) ───────────

    /**
     * Install + activate a plugin: record install state (first time runs
     * install()), then activate(). Idempotent — re-running only re-activates.
     *
     * @return array{0:bool,1:string} [ok, message]
     */
    public function install(string $id): array
    {
        $plugin = $this->resolve($id);

        if (! $plugin) {
            return [false, "Plugin [{$id}] was not found (check it's on disk / discoverable)."];
        }

        $state = \Modules\Hook\Models\PluginState::firstOrNew(['plugin_id' => $id]);
        $firstInstall = ! $state->exists;

        if ($firstInstall) {
            $plugin->install();   // migrations, seed defaults — only once
            $state->installed_at = now();
        }

        $plugin->activate();

        $state->version = $plugin->version();
        $state->active = true;
        $state->save();

        return [true, $firstInstall
            ? "Installed and activated [{$id}] {$plugin->version()}."
            : "Re-activated [{$id}] {$plugin->version()}."];
    }

    /**
     * Deactivate a plugin but keep its data. Idempotent.
     *
     * @return array{0:bool,1:string}
     */
    public function disable(string $id): array
    {
        $plugin = $this->resolve($id);
        $state = \Modules\Hook\Models\PluginState::where('plugin_id', $id)->first();

        if (! $state) {
            return [false, "Plugin [{$id}] is not installed."];
        }

        $plugin?->deactivate();
        $state->update(['active' => false]);

        return [true, "Disabled [{$id}] (data kept). Remove it from config('plugins.enabled') to stop loading."];
    }

    /**
     * Fully uninstall: deactivate, run uninstall() (rollback/cleanup), drop the
     * install record. Destructive — callers must confirm.
     *
     * @return array{0:bool,1:string}
     */
    public function uninstall(string $id): array
    {
        $plugin = $this->resolve($id);
        $state = \Modules\Hook\Models\PluginState::where('plugin_id', $id)->first();

        if (! $state) {
            return [false, "Plugin [{$id}] is not installed."];
        }

        $plugin?->deactivate();
        $plugin?->uninstall();
        $state->delete();

        return [true, "Uninstalled [{$id}]."];
    }

    /**
     * A row-per-discovered-plugin summary for `plugin:list`.
     *
     * @return list<array{id:string,version:string,enabled:bool,installed:bool,active:bool,satisfied:bool}>
     */
    public function status(): array
    {
        $enabled = (array) config('plugins.enabled', []);
        $available = ['core' => (string) config('plugins.core_version', '0.0.0')];

        // Before the plugins table is migrated, treat everything as not-installed
        // rather than crashing `plugin:list` on a fresh app.
        $states = \Illuminate\Support\Facades\Schema::hasTable('plugins')
            ? \Modules\Hook\Models\PluginState::all()->keyBy('plugin_id')
            : collect();

        return collect($this->discover())->map(function (Plugin $plugin) use ($enabled, $available, $states) {
            $state = $states->get($plugin->id());

            return [
                'id' => $plugin->id(),
                'version' => $plugin->version(),
                'enabled' => in_array($plugin->id(), $enabled, true),
                'installed' => (bool) $state,
                'active' => (bool) ($state?->active),
                'satisfied' => $this->satisfied($plugin, $available),
            ];
        })->all();
    }

    /**
     * Find candidate plugin instances from all sources (no allow-list yet).
     *
     * @return list<Plugin>
     */
    public function discover(): array
    {
        $providers = array_merge(
            $this->discoverFromComposer(),
            $this->discoverFromPaths(),
        );

        $plugins = [];

        foreach (array_unique($providers) as $class) {
            $plugin = $this->instantiate($class);

            if ($plugin) {
                $plugins[] = $plugin;
            }
        }

        return $plugins;
    }

    /**
     * Plugin provider classes declared by installed composer packages via
     * `extra.lunar-sme.plugin` in their composer.json (collected into the
     * installed-packages manifest).
     *
     * @return list<class-string>
     */
    protected function discoverFromComposer(): array
    {
        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return [];
        }

        $installed = json_decode((string) file_get_contents($path), true);
        $packages = $installed['packages'] ?? $installed ?? [];

        return collect($packages)
            ->pluck('extra.lunar-sme.plugin')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Plugin provider classes from `plugin.json` manifests under the configured
     * paths (plugins/<vendor>/<name>/plugin.json).
     *
     * @return list<class-string>
     */
    protected function discoverFromPaths(): array
    {
        $classes = [];

        foreach ((array) config('plugins.paths', []) as $base) {
            foreach (glob(rtrim($base, '/') . '/*/*/plugin.json') ?: [] as $manifestPath) {
                $manifest = json_decode((string) file_get_contents($manifestPath), true);

                if (is_array($manifest) && ! empty($manifest['provider'])) {
                    $classes[] = $manifest['provider'];
                }
            }
        }

        return $classes;
    }

    /** Instantiate a plugin class, logging+skipping anything invalid. */
    protected function instantiate(string $class): ?Plugin
    {
        if (! class_exists($class)) {
            Log::warning("Plugin class [{$class}] not found — skipped.");

            return null;
        }

        $instance = $this->app->make($class);

        if (! $instance instanceof Plugin) {
            Log::warning("Plugin class [{$class}] does not implement the Plugin contract — skipped.");

            return null;
        }

        return $instance;
    }

    /**
     * Topologically sort plugins so dependencies load before dependents. A
     * dependency that isn't an enabled plugin (e.g. `core`, or an external lib)
     * is ignored here — version satisfaction is checked separately in
     * {@see satisfied()}. Cycles are broken deterministically (offending plugin
     * appended) and logged.
     *
     * @param  array<string, Plugin>  $plugins  id => instance
     * @return list<Plugin>
     */
    protected function order(array $plugins): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (Plugin $plugin) use (&$visit, &$sorted, &$visiting, $plugins): void {
            $id = $plugin->id();

            if (isset($sorted[$id])) {
                return;
            }

            if (isset($visiting[$id])) {
                Log::warning("Plugin dependency cycle detected at [{$id}] — broken arbitrarily.");

                return;
            }

            $visiting[$id] = true;

            foreach (array_keys($plugin->requires()) as $dep) {
                if (isset($plugins[$dep])) {
                    $visit($plugins[$dep]);
                }
            }

            unset($visiting[$id]);
            $sorted[$id] = $plugin;
        };

        foreach ($plugins as $plugin) {
            $visit($plugin);
        }

        return array_values($sorted);
    }

    /**
     * Whether every requirement of $plugin is present and version-satisfied,
     * given the plugins/core already available. Logs the first failure.
     *
     * @param  array<string, string>  $available  id => version (incl. 'core')
     */
    protected function satisfied(Plugin $plugin, array $available): bool
    {
        foreach ($plugin->requires() as $dep => $constraint) {
            $version = $available[$dep] ?? null;

            if ($version === null) {
                Log::warning("Plugin [{$plugin->id()}] requires [{$dep}] which is not available — skipped.");

                return false;
            }

            if (! Semver::satisfies($version, $constraint)) {
                Log::warning(
                    "Plugin [{$plugin->id()}] requires [{$dep} {$constraint}] but [{$version}] is installed — skipped."
                );

                return false;
            }
        }

        return true;
    }
}
