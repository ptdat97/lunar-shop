<?php

namespace Modules\Platform\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Modules\Platform\Plugin\PluginConfig;
use Modules\Platform\Plugin\PluginManager;

/**
 * Admin Plugins page: a management table of every discovered plugin (enabled /
 * installed / active) with install/disable/uninstall actions, plus a config
 * form whose tabs are CONTRIBUTED by the plugins themselves — any enabled
 * plugin implementing {@see PluginConfig} gets its own tab here, with state
 * namespaced under its id so two plugins never clash.
 */
class PluginsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $slug = 'plugins';

    protected static string $view = 'platform::filament.plugins';

    public static function getNavigationLabel(): string
    {
        return 'Plugins';
    }

    public function getTitle(): string
    {
        return 'Plugins';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('lunarpanel::global.sections.settings');
    }

    /** @var array<string, mixed> per-plugin config state, keyed by plugin id */
    public array $data = [];

    public function mount(): void
    {
        // Assign directly (rather than form->fill) so the per-plugin nested
        // state under each Section's statePath is populated reliably.
        $this->data = $this->collectState();
    }

    /**
     * One tab per enabled config-providing plugin. Field state is nested under
     * the plugin id (dotted keys are escaped) so plugins stay isolated.
     */
    public function form(Form $form): Form
    {
        $tabs = [];

        foreach ($this->configPlugins() as $id => $plugin) {
            $tabs[] = Tabs\Tab::make($plugin->configLabel())
                ->schema([
                    Section::make()
                        ->statePath($this->statePath($id))
                        ->schema($plugin->configSchema()),
                ]);
        }

        if (! $tabs) {
            return $form->schema([])->statePath('data');
        }

        return $form
            ->statePath('data')
            ->schema([Tabs::make('plugin-config')->tabs($tabs)->columnSpanFull()]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($this->configPlugins() as $id => $plugin) {
            $plugin->saveConfig((array) ($state[$this->statePath($id)] ?? []));
        }

        Notification::make()->title('Plugin settings saved.')->success()->send();
    }

    /** Rows for the management table in the view. */
    public function pluginRows(): array
    {
        return app(PluginManager::class)->status();
    }

    public function hasConfigTabs(): bool
    {
        return $this->configPlugins() !== [];
    }

    // ─── Table actions (install / disable / uninstall) ───────────────────────

    public function installPlugin(string $id): void
    {
        [$ok, $message] = app(PluginManager::class)->install($id);
        $this->notify($ok, $message);
    }

    public function disablePlugin(string $id): void
    {
        [$ok, $message] = app(PluginManager::class)->disable($id);
        $this->notify($ok, $message);
    }

    public function uninstallPlugin(string $id): void
    {
        [$ok, $message] = app(PluginManager::class)->uninstall($id);
        $this->notify($ok, $message);
    }

    protected function notify(bool $ok, string $message): void
    {
        Notification::make()->title($message)->{$ok ? 'success' : 'danger'}()->send();
    }

    /**
     * Enabled, config-providing plugins (the ones that load and offer a tab).
     *
     * @return array<string, PluginConfig>
     */
    protected function configPlugins(): array
    {
        return app(PluginManager::class)->configPlugins();
    }

    /** @return array<string, mixed> */
    protected function collectState(): array
    {
        $state = [];

        foreach ($this->configPlugins() as $id => $plugin) {
            $state[$this->statePath($id)] = $plugin->configState();
        }

        return $state;
    }

    /** A Livewire-safe state key for a plugin id (no dots/slashes). */
    protected function statePath(string $id): string
    {
        return str_replace(['/', '.'], '__', $id);
    }
}
