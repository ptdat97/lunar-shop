<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Modules\Hook\Plugin\PluginManager;
use Tests\Fixtures\Plugins\DemoPlugin;
use Tests\TestCase;

/**
 * E1 — the Plugin SDK runtime: discovery from plugin.json manifests, the
 * allow-list gate, dependency ordering, version satisfaction, and fail-soft
 * skipping. Fixtures live under tests/Fixtures/plugins + tests/Fixtures/Plugins.
 */
class PluginManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DemoPlugin::$trace = [];

        config([
            'plugins.paths' => [base_path('tests/Fixtures/plugins')],
            'plugins.core_version' => '1.0.0',
        ]);
    }

    protected function manager(): PluginManager
    {
        return new PluginManager($this->app);
    }

    public function test_discovers_plugins_from_manifests(): void
    {
        $ids = collect($this->manager()->discover())->map->id()->sort()->values()->all();

        $this->assertSame(['acme/addon', 'acme/demo', 'acme/future'], $ids);
    }

    public function test_only_enabled_plugins_load(): void
    {
        config(['plugins.enabled' => ['acme/demo']]);

        $loaded = $this->manager()->load();

        $this->assertSame(['acme/demo'], $loaded);
        $this->assertContains('register:acme/demo', DemoPlugin::$trace);
    }

    public function test_a_plugin_off_the_allow_list_does_not_load(): void
    {
        config(['plugins.enabled' => []]);

        $this->assertSame([], $this->manager()->load());
        $this->assertSame([], DemoPlugin::$trace);
    }

    public function test_dependencies_load_before_dependents(): void
    {
        // Enabled in the "wrong" order on purpose — the manager must reorder.
        config(['plugins.enabled' => ['acme/addon', 'acme/demo']]);

        $loaded = $this->manager()->load();

        $this->assertSame(['acme/demo', 'acme/addon'], $loaded);
        $this->assertSame(
            array_search('register:acme/demo', DemoPlugin::$trace, true) <
            array_search('register:acme/addon', DemoPlugin::$trace, true),
            true,
        );
    }

    public function test_a_plugin_with_a_missing_dependency_is_skipped(): void
    {
        // addon needs acme/demo, but only addon is enabled.
        config(['plugins.enabled' => ['acme/addon']]);

        Log::spy();

        $this->assertSame([], $this->manager()->load());
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $msg) => str_contains($msg, 'acme/addon') && str_contains($msg, 'acme/demo')
        )->once();
    }

    public function test_an_incompatible_core_version_is_skipped(): void
    {
        config(['plugins.enabled' => ['acme/future']]); // needs core ^2.0, app is 1.0.0

        Log::spy();

        $this->assertSame([], $this->manager()->load());
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $msg) => str_contains($msg, 'acme/future') && str_contains($msg, 'core')
        )->once();
    }

    public function test_boot_runs_loaded_plugins_and_their_hooks_fire(): void
    {
        config(['plugins.enabled' => ['acme/demo']]);

        $manager = $this->manager();
        $manager->load();
        $manager->boot();

        $this->assertContains('boot:acme/demo', DemoPlugin::$trace);

        // The listener the plugin registered in boot() is live.
        app(\Modules\Hook\Services\HookManager::class)->doAction('demo.ping');
        $this->assertContains('ping:acme/demo', DemoPlugin::$trace);
    }

    // ─── E2: lifecycle + state ───────────────────────────────────────────────

    public function test_install_records_state_and_runs_lifecycle_once(): void
    {
        [$ok, $message] = $this->manager()->install('acme/demo');

        $this->assertTrue($ok);
        $this->assertContains('install:acme/demo', DemoPlugin::$trace);
        $this->assertContains('activate:acme/demo', DemoPlugin::$trace);
        $this->assertDatabaseHas('plugins', [
            'plugin_id' => 'acme/demo',
            'version' => '1.0.0',
            'active' => true,
        ]);

        // Re-install is idempotent: install() not run again, only activate().
        DemoPlugin::$trace = [];
        [$ok2] = $this->manager()->install('acme/demo');

        $this->assertTrue($ok2);
        $this->assertNotContains('install:acme/demo', DemoPlugin::$trace);
        $this->assertContains('activate:acme/demo', DemoPlugin::$trace);
        $this->assertSame(1, \Modules\Hook\Models\PluginState::where('plugin_id', 'acme/demo')->count());
    }

    public function test_installing_an_unknown_plugin_fails_cleanly(): void
    {
        [$ok, $message] = $this->manager()->install('acme/ghost');

        $this->assertFalse($ok);
        $this->assertStringContainsString('not found', $message);
        $this->assertDatabaseMissing('plugins', ['plugin_id' => 'acme/ghost']);
    }

    public function test_disable_keeps_data_but_marks_inactive(): void
    {
        $this->manager()->install('acme/demo');
        DemoPlugin::$trace = [];

        [$ok] = $this->manager()->disable('acme/demo');

        $this->assertTrue($ok);
        $this->assertContains('deactivate:acme/demo', DemoPlugin::$trace);
        $this->assertDatabaseHas('plugins', ['plugin_id' => 'acme/demo', 'active' => false]);
    }

    public function test_uninstall_runs_cleanup_and_drops_the_record(): void
    {
        $this->manager()->install('acme/demo');
        DemoPlugin::$trace = [];

        [$ok] = $this->manager()->uninstall('acme/demo');

        $this->assertTrue($ok);
        $this->assertContains('uninstall:acme/demo', DemoPlugin::$trace);
        $this->assertDatabaseMissing('plugins', ['plugin_id' => 'acme/demo']);
    }

    public function test_status_reports_enabled_installed_active_flags(): void
    {
        config(['plugins.enabled' => ['acme/demo']]);
        $this->manager()->install('acme/demo');

        $status = collect($this->manager()->status())->keyBy('id');

        $this->assertTrue($status['acme/demo']['enabled']);
        $this->assertTrue($status['acme/demo']['installed']);
        $this->assertTrue($status['acme/demo']['active']);
        $this->assertFalse($status['acme/future']['installed']);
    }

    public function test_install_command_runs_lifecycle(): void
    {
        $this->artisan('plugin:install', ['id' => 'acme/demo'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('plugins', ['plugin_id' => 'acme/demo', 'active' => true]);
    }

    public function test_uninstall_command_aborts_without_force_when_declined(): void
    {
        $this->manager()->install('acme/demo');

        $this->artisan('plugin:uninstall', ['id' => 'acme/demo'])
            ->expectsConfirmation("This rolls back [acme/demo]'s data. Continue?", 'no')
            ->assertExitCode(0);

        $this->assertDatabaseHas('plugins', ['plugin_id' => 'acme/demo']);
    }

    // ─── E4: diagnose / doctor ───────────────────────────────────────────────

    public function test_diagnose_reports_no_issues_for_healthy_plugins(): void
    {
        config(['plugins.enabled' => ['acme/demo']]);

        $this->assertSame([], $this->manager()->diagnose());
    }

    public function test_diagnose_flags_a_missing_dependency(): void
    {
        // addon needs acme/demo, but only addon is enabled.
        config(['plugins.enabled' => ['acme/addon']]);

        $issues = $this->manager()->diagnose();

        $this->assertCount(1, $issues);
        $this->assertSame('acme/addon', $issues[0]['plugin']);
        $this->assertStringContainsString('acme/demo', $issues[0]['issue']);
    }

    public function test_diagnose_flags_an_incompatible_core_version(): void
    {
        config(['plugins.enabled' => ['acme/future']]); // needs core ^2.0

        $issues = $this->manager()->diagnose();

        $this->assertCount(1, $issues);
        $this->assertStringContainsString('core', $issues[0]['issue']);
    }

    public function test_doctor_command_exit_codes(): void
    {
        config(['plugins.enabled' => ['acme/demo']]);
        $this->artisan('plugin:doctor')->assertExitCode(0);

        config(['plugins.enabled' => ['acme/future']]);
        $this->artisan('plugin:doctor')->assertExitCode(1);
    }
}
