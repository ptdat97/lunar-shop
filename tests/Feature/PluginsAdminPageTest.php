<?php

namespace Tests\Feature;

use Livewire\Livewire;
use Modules\Platform\Filament\Pages\PluginsPage;
use Modules\Platform\Plugin\PluginManager;
use Modules\Platform\Plugin\PluginSettings;
use Tests\TestCase;

/**
 * The admin Plugins page: a management table of discovered plugins, plus config
 * tabs CONTRIBUTED by enabled plugins (PluginConfig). Reviews + Preorder each
 * add a tab; saving the form persists through their saveConfig().
 */
class PluginsAdminPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['plugins.enabled' => ['acme/reviews', 'acme/preorder']]);

        // Install + load + boot the plugins so their config tabs are available,
        // mirroring app boot once they're enabled.
        $manager = new PluginManager($this->app);
        $manager->install('acme/reviews');
        $manager->install('acme/preorder');
        $manager->load();
        $manager->boot();
    }

    public function test_page_lists_discovered_plugins(): void
    {
        $rows = collect(app(PluginsPage::class)->pluginRows())->keyBy('id');

        $this->assertTrue($rows->has('acme/reviews'));
        $this->assertTrue($rows['acme/reviews']['enabled']);
        $this->assertTrue($rows['acme/reviews']['active']);
    }

    public function test_each_enabled_config_plugin_contributes_a_tab(): void
    {
        $page = app(PluginsPage::class);

        $this->assertTrue($page->hasConfigTabs());
    }

    public function test_saving_the_form_persists_each_plugin_config(): void
    {
        Livewire::test(PluginsPage::class)
            ->set('data.acme__reviews.auto_approve', false)
            ->set('data.acme__preorder.label', 'Coming soon')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(PluginSettings::for('acme/reviews')->get('auto_approve'));
        $this->assertSame('Coming soon', PluginSettings::for('acme/preorder')->get('label'));
    }

    public function test_install_and_disable_actions_work_from_the_page(): void
    {
        Livewire::test(PluginsPage::class)
            ->call('disablePlugin', 'acme/reviews');

        $this->assertDatabaseHas('plugins', ['plugin_id' => 'acme/reviews', 'active' => false]);

        Livewire::test(PluginsPage::class)
            ->call('installPlugin', 'acme/reviews');

        $this->assertDatabaseHas('plugins', ['plugin_id' => 'acme/reviews', 'active' => true]);
    }
}
